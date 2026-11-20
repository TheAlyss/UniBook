<?php

namespace App\Models;

use App\Support\Covers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A book in UniBook's own catalogue (step 1 of the book lookup). */
class Book extends Model
{
    public const SOURCES = ['google_books', 'open_library', 'manual'];

    protected $fillable = [
        'isbn', 'title', 'author', 'edition', 'publisher', 'published_year',
        'cover_url', 'cover_source', 'source',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function coverImageUrl(): ?string
    {
        return Covers::url($this->cover_url);
    }

    /** Same shape as a Google Books / Open Library result, so the UI treats all sources alike. */
    public function toLookupResult(): array
    {
        return [
            'title' => $this->title,
            'author' => $this->author,
            'edition' => $this->edition,
            'publisher' => $this->publisher,
            'published_year' => $this->published_year,
            'isbn' => $this->isbn,
            'cover_url' => $this->coverImageUrl(),
            'cover_path' => $this->cover_url,
            'cover_source' => $this->cover_source,
            'source' => 'UniBook catalogue',
            'source_key' => 'catalog',
            'book_id' => $this->id,
        ];
    }
}
