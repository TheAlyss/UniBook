<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** UniBook listing schema used by the API. */
class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'edition' => $this->edition,
            'publisher' => $this->publisher,
            'isbn' => $this->isbn,
            'cover_url' => $this->coverImageUrl(),
            'cover_source' => $this->cover_source,
            'book_id' => $this->book_id,
            'category' => $this->category,
            'course_code' => $this->course_code,
            'price' => (float) $this->price,
            'condition' => $this->condition,
            'description' => $this->description,
            'status' => $this->status,
            'seller' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'url' => route('books.show', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
