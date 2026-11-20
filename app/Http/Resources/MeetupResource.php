<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** UniBook meetup schema used by the API. */
class MeetupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'point' => $this->point,
            'name' => $this->name,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'meetup_time' => $this->meetup_time?->toIso8601String(),
            'from' => $this->hasOrigin()
                ? ['place' => $this->from_place, 'lat' => $this->from_lat, 'lng' => $this->from_lng]
                : null,
            'distance_m' => $this->distance_m,
            'duration_s' => $this->duration_s,
            'route_url' => url("/api/meetups/{$this->id}/route"),
        ];
    }
}
