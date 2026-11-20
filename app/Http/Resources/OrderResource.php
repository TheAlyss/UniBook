<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** UniBook order schema used by the API. */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'listing' => ['id' => $this->listing_id, 'title' => $this->listing_title],
            'amount' => (float) $this->amount,
            'payment' => 'cash_on_meetup',
            'note' => $this->note,
            'buyer' => $this->whenLoaded('buyer', fn () => ['id' => $this->buyer->id, 'name' => $this->buyer->name]),
            'seller' => $this->whenLoaded('seller', fn () => ['id' => $this->seller->id, 'name' => $this->seller->name]),
            'meetup' => $this->whenLoaded('meetup', fn () => $this->meetup ? new MeetupResource($this->meetup) : null),
            'rating' => $this->rating,
            'review' => $this->review,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
