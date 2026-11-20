<?php

namespace Tests\Feature;

use App\Services\MapsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests: MapsService against faked Nominatim and OSRM responses.
 */
class MapsServiceTest extends TestCase
{
    public function test_geocode_maps_nominatim_result_and_sends_user_agent(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '6.4310', 'lon' => '100.2700', 'display_name' => 'Arau, Perlis, Malaysia'],
        ])]);

        $place = app(MapsService::class)->geocode('Arau');

        $this->assertSame(['lat' => 6.431, 'lng' => 100.27, 'name' => 'Arau, Perlis, Malaysia'], $place);
        Http::assertSent(fn (Request $r) => $r->hasHeader('User-Agent') && $r['q'] === 'Arau' && $r['countrycodes'] === 'my');
    }

    public function test_geocode_returns_null_when_place_is_unknown_and_caches_hits(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([])
            ->push([['lat' => '6.44', 'lon' => '100.19', 'display_name' => 'Kangar']])]);

        $maps = app(MapsService::class);
        $this->assertNull($maps->geocode('Nowhere Land'));
        $maps->geocode('Kangar');
        $maps->geocode('kangar'); // cached

        Http::assertSentCount(2);
    }

    public function test_suggestions_are_sorted_by_osrm_road_distance(): void
    {
        $count = count(config('unibook.meetup_points'));
        // Origin first, then one distance per meetup point: make the LAST point the nearest.
        $distances = [0];
        for ($i = 0; $i < $count; $i++) {
            $distances[] = 2000 - $i * 100;
        }

        Http::fake(['router.project-osrm.org/table/*' => Http::response([
            'code' => 'Ok',
            'distances' => [$distances],
            'durations' => [array_map(fn ($d) => $d / 10, $distances)],
        ])]);

        $points = app(MapsService::class)->suggestMeetupPoints(6.45, 100.28);

        $this->assertSame(array_key_last(config('unibook.meetup_points')), $points[0]['key']);
        $this->assertSame('road', $points[0]['method']);
        $this->assertSame(2000 - ($count - 1) * 100, $points[0]['distance_m']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/table/v1/driving/100.28,6.45;') && (int) $r['sources'] === 0);
    }

    public function test_suggestions_fall_back_to_straight_line_when_osrm_is_down(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $library = config('unibook.meetup_points.library');
        $points = app(MapsService::class)->suggestMeetupPoints($library['lat'], $library['lng']);

        $this->assertSame('library', $points[0]['key']);
        $this->assertSame(0, $points[0]['distance_m']);
        $this->assertSame('straight_line', $points[0]['method']);
        $this->assertNull($points[0]['duration_s']);
    }

    public function test_route_converts_geojson_to_lat_lng_pairs(): void
    {
        Http::fake(['router.project-osrm.org/route/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['distance' => 432.5, 'duration' => 62.5, 'geometry' => ['coordinates' => [[100.2795, 6.4471], [100.2811, 6.4498]]]]],
        ])]);

        $route = app(MapsService::class)->route(6.4471, 100.2795, 6.4498, 100.2811);

        $this->assertSame(433, $route['distance_m']);
        $this->assertSame(63, $route['duration_s']);
        $this->assertSame([[6.4471, 100.2795], [6.4498, 100.2811]], $route['geometry']);
    }

    public function test_straight_line_distance(): void
    {
        // Mosque -> main campus point: about 350 m in a straight line (OSRM says 433 m by road).
        $this->assertEqualsWithDelta(350, MapsService::straightLineMetres(6.4471716, 100.2795513, 6.4498715, 100.2811541), 5);
        $this->assertSame(0, MapsService::straightLineMetres(6.45, 100.28, 6.45, 100.28));
    }
}
