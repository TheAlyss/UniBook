<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** status value => [label, Bootstrap badge colour] */
    public const STATUSES = [
        'pending' => ['Booked', 'warning'],
        'completed' => ['Completed', 'success'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    /** Orders that still hold the listing (it cannot be booked by someone else). */
    public const ACTIVE = ['pending'];

    protected $fillable = [
        'listing_id', 'listing_title', 'buyer_id', 'seller_id', 'amount', 'status', 'note',
        'completed_at', 'cancelled_at', 'rating', 'review', 'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rated_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function meetup(): HasOne
    {
        return $this->hasOne(Meetup::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'light';
    }

    public function isParty(User $user): bool
    {
        return in_array($user->id, [$this->buyer_id, $this->seller_id], true);
    }
}
