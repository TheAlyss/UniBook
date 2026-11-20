<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Where and when the buyer and seller meet (one per order). */
class Meetup extends Model
{
    protected $fillable = [
        'order_id', 'point', 'name', 'lat', 'lng', 'meetup_time',
        'from_place', 'from_lat', 'from_lng', 'distance_m', 'duration_s',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'from_lat' => 'float',
            'from_lng' => 'float',
            'meetup_time' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function hasOrigin(): bool
    {
        return $this->from_lat !== null && $this->from_lng !== null;
    }

    /** "1.2 km" / "450 m" */
    public function distanceLabel(): ?string
    {
        if ($this->distance_m === null) {
            return null;
        }

        return $this->distance_m >= 1000
            ? number_format($this->distance_m / 1000, 1) . ' km'
            : $this->distance_m . ' m';
    }

    /** "3 min" */
    public function durationLabel(): ?string
    {
        return $this->duration_s === null ? null : max(1, (int) round($this->duration_s / 60)) . ' min';
    }
}
