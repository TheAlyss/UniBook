<?php

namespace App\Models;

use App\Support\Covers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    public const CONDITIONS = ['Like new', 'Good', 'Fair'];

    public const CATEGORIES = ['Textbook', 'Reference', 'Novel', 'Religious', 'Others'];

    /** status value => [label, Bootstrap badge colour] */
    public const STATUSES = [
        'available' => ['Available', 'success'],
        'reserved' => ['Reserved', 'warning'],
        'sold' => ['Sold', 'secondary'],
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'light';
    }

    protected $fillable = [
        'book_id', 'title', 'author', 'edition', 'publisher', 'isbn', 'cover_url', 'cover_source',
        'course_code', 'category', 'price', 'condition', 'description', 'status',
    ];

    protected static function booted(): void
    {
        // "Delete removes cache and image": remove the seller's photo unless something else still uses it.
        static::deleted(function (Listing $listing) {
            $path = $listing->cover_url;
            if (! Covers::isStoredPhoto($path)) {
                return;
            }
            $stillUsed = static::where('cover_url', $path)->exists() || Book::where('cover_url', $path)->exists();
            if (! $stillUsed) {
                Covers::deletePhoto($path);
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** Browser URL of the cover, or null to show the placeholder. */
    public function coverImageUrl(): ?string
    {
        return Covers::url($this->cover_url);
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** True while a buyer has booked this listing and the deal is not finished or cancelled. */
    public function hasActiveOrder(): bool
    {
        return $this->orders()->active()->exists();
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /**
     * One search box: matches title, author, ISBN (hyphens/spaces ignored) or course code.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';
        $digits = preg_replace('/[\s-]/', '', $term);

        return $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('title', 'like', $like)
                ->orWhere('author', 'like', $like)
                ->orWhere('course_code', 'like', str_replace(' ', '', $like));

            if (preg_match('/^\d{4,12}[\dXx]?$/', $digits)) {
                $q->orWhere('isbn', 'like', "%{$digits}%");
            }
        });
    }

    public function scopeInCategory(Builder $query, ?string $category): Builder
    {
        return in_array($category, self::CATEGORIES, true) ? $query->where('category', $category) : $query;
    }

    public static function courseCodes(): array
    {
        return static::available()->whereNotNull('course_code')->distinct()->orderBy('course_code')->pluck('course_code')->all();
    }
}
