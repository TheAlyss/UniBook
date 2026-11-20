<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Order;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /** Home page: one search box (title, author, ISBN, course code) + category filter. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');
        if (! in_array($category, Listing::CATEGORIES, true)) {
            $category = '';
        }

        $sort = (string) $request->query('sort', 'newest');
        if (! array_key_exists($sort, self::SORTS)) {
            $sort = 'newest';
        }

        $books = Listing::available()
            ->with('user')
            ->search($q)
            ->inCategory($category)
            ->when($sort === 'price_asc', fn ($query) => $query->orderBy('price'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('price'))
            ->latest()
            ->get();

        return view('books.index', [
            'books' => $books,
            'q' => $q,
            'category' => $category,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'categories' => Listing::CATEGORIES,
            'ratings' => self::sellerRatings($books->pluck('user_id')->unique()->all()),
        ]);
    }

    /** Sort options on the home page: value => label. */
    public const SORTS = ['newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];

    /**
     * Average rating per seller in ONE query (instead of one query per book card).
     *
     * @param list<int> $sellerIds
     * @return array<int, float> seller id => average rating (sellers without ratings are left out)
     */
    public static function sellerRatings(array $sellerIds): array
    {
        if ($sellerIds === []) {
            return [];
        }

        return Order::whereIn('seller_id', $sellerIds)
            ->whereNotNull('rating')
            ->groupBy('seller_id')
            ->selectRaw('seller_id, AVG(rating) as average')
            ->pluck('average', 'seller_id')
            ->map(fn ($avg) => round((float) $avg, 1))
            ->all();
    }

    public function show(Request $request, Listing $listing)
    {
        $listing->load('user');
        // Hidden by an admin or seller banned: only the seller and admins can still open it.
        abort_unless($listing->visibleTo($request->user()), 404);

        // Same course if the book has one; if there are none (or no course), the same category.
        $relatedBy = fn (string $column, string $value) => Listing::available()
            ->where($column, $value)
            ->whereKeyNot($listing->id)
            ->with('user')
            ->latest()
            ->limit(8)
            ->get();
        $related = $listing->course_code ? $relatedBy('course_code', $listing->course_code) : collect();
        $relatedLabel = $listing->course_code;
        if ($related->isEmpty()) {
            $related = $relatedBy('category', $listing->category);
            $relatedLabel = $listing->category;
        }

        $label = $listing->course_code ?: $listing->category;
        $shareText = "For sale on UniBook: {$listing->title} ({$label}) - RM{$listing->price}\n"
            . route('books.show', $listing);

        return view('books.show', [
            'book' => $listing,
            'related' => $related,
            'relatedLabel' => ($relatedLabel === $listing->course_code ? 'for ' : 'in ') . $relatedLabel,
            'sellerRating' => $listing->user->sellerRating(),
            'sellerSold' => $listing->user->sales()->where('status', 'completed')->count(),
            'relatedRatings' => self::sellerRatings($related->pluck('user_id')->unique()->all()),
            'whatsappUrl' => 'https://wa.me/?text=' . rawurlencode($shareText),
        ]);
    }
}
