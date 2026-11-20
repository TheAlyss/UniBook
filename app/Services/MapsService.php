<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only class in UniBook that talks to OpenStreetMap services.
 *
 * - Nominatim: place name -> coordinates (max 1 request per second, User-Agent required)
 * - OSRM: road distance, travel time and route line between two points
 *
 * If OSRM is down, distances fall back to a straight-line estimate so booking still works.
 */
class MapsService
{
    /** @return array<string, array{key:string,name:string,lat:float,lng:float}> */
    public function meetupPoints(): array
    {
        return collect(config('unibook.meetup_points'))
            ->map(fn (array $p, string $key) => ['key' => $key] + $p)
            ->all();
    }

    public function meetupPoint(string $key): ?array
    {
        return $this->meetupPoints()[$key] ?? null;
    }

    /**
     * Place name -> ['lat' => .., 'lng' => .., 'name' => ..] or null.
     * Results are biased towards the Arau campus and cached for a week.
     */
    public function geocode(string $place): ?array
    {
        $place = trim($place);
        if ($place === '') {
            return null;
        }

        $cacheKey = 'geocode:' . md5(mb_strtolower($place));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $campus = config('unibook.campus');
        $this->waitForNominatimSlot();

        try {
            $response = $this->http()->get(config('services.nominatim.url') . '/search', [
                'q' => $place,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => 'my',
                // Prefer results around the campus, but don't exclude the rest of Malaysia.
                'viewbox' => implode(',', [$campus['lng'] - 0.05, $campus['lat'] + 0.05, $campus['lng'] + 0.05, $campus['lat'] - 0.05]),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Nominatim unreachable', ['error' => $e->getMessage()]);
            return null;
        }

        $first = $response->successful() ? ($response->json()[0] ?? null) : null;
        if ($first === null) {
            if ($response->failed()) {
                Log::warning('Nominatim request failed', ['status' => $response->status()]);
            }
            return null;
        }

        $result = [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
            'name' => $first['display_name'] ?? $place,
        ];
        Cache::put($cacheKey, $result, now()->addWeek());

        return $result;
    }

    /**
     * All meetup points sorted by distance from the buyer.
     * Uses one OSRM "table" request; falls back to straight-line distance.
     *
     * @return array<int, array{key:string,name:string,lat:float,lng:float,distance_m:int,duration_s:?int,method:string}>
     */
    public function suggestMeetupPoints(float $lat, float $lng): array
    {
        $points = array_values($this->meetupPoints());
        $coords = collect([[$lng, $lat]])
            ->merge(array_map(fn ($p) => [$p['lng'], $p['lat']], $points))
            ->map(fn ($c) => $c[0] . ',' . $c[1])
            ->implode(';');

        $table = null;
        try {
            $response = $this->http()->get(config('services.osrm.url') . "/table/v1/driving/{$coords}", [
                'sources' => 0,
                'annotations' => 'distance,duration',
            ]);
            if ($response->successful() && $response->json('code') === 'Ok') {
                $table = $response->json();
            } else {
                Log::warning('OSRM table request failed', ['status' => $response->status()]);
            }
        } catch (ConnectionException $e) {
            Log::warning('OSRM unreachable', ['error' => $e->getMessage()]);
        }

        return collect($points)
            ->map(function (array $p, int $i) use ($table, $lat, $lng) {
                $distance = $table['distances'][0][$i + 1] ?? null;
                $duration = $table['durations'][0][$i + 1] ?? null;

                return $p + ($distance !== null
                    ? ['distance_m' => (int) round($distance), 'duration_s' => (int) round($duration), 'method' => 'road']
                    : ['distance_m' => self::straightLineMetres($lat, $lng, $p['lat'], $p['lng']), 'duration_s' => null, 'method' => 'straight_line']);
            })
            ->sortBy('distance_m')
            ->values()
            ->all();
    }

    /**
     * Road route between two points: ['distance_m', 'duration_s', 'geometry' => [[lat, lng], ...]] or null.
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        try {
            $response = $this->http()->get(
                config('services.osrm.url') . "/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}",
                ['overview' => 'full', 'geometries' => 'geojson']
            );
        } catch (ConnectionException $e) {
            Log::warning('OSRM unreachable', ['error' => $e->getMessage()]);
            return null;
        }

        $route = $response->successful() ? $response->json('routes.0') : null;
        if ($route === null) {
            return null;
        }

        return [
            'distance_m' => (int) round($route['distance']),
            'duration_s' => (int) round($route['duration']),
            // GeoJSON is [lng, lat]; Leaflet wants [lat, lng].
            'geometry' => array_map(fn ($c) => [$c[1], $c[0]], $route['geometry']['coordinates'] ?? []),
        ];
    }

    /** Haversine distance in metres. */
    public static function straightLineMetres(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function http()
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders(['User-Agent' => config('services.osm.user_agent')]);
    }

    /** Nominatim usage policy: at most 1 request per second. */
    private function waitForNominatimSlot(): void
    {
        $last = (float) Cache::get('nominatim:last_request', 0);
        $wait = 1.0 - (microtime(true) - $last);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        Cache::put('nominatim:last_request', microtime(true), 10);
    }
}
