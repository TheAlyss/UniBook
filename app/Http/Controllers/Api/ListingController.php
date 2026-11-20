<?php

namespace App\Http\Controllers\Api;

use App\Actions\ScanBook;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\BookLookupService;
use App\Services\BooksService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Module A (Scan & Listing, member A) and Module B (Catalogue, member B) REST endpoints.
 */
class ListingController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/listings?q=...&category=...  (B: Read)
     * "q" matches title, author, ISBN or course code. "course" is still accepted as an alias of "q".
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'course' => ['nullable', 'string', 'max:10'],
            'category' => ['nullable', 'in:' . implode(',', Listing::CATEGORIES)],
        ], [
            'category.in' => 'Unknown category. Valid categories: ' . implode(', ', Listing::CATEGORIES) . '.',
        ]);
        $q = trim((string) ($data['q'] ?? $data['course'] ?? ''));
        $category = $data['category'] ?? null;

        $listings = Listing::available()
            ->with('user')
            ->search($q)
            ->inCategory($category)
            ->latest()
            ->get();

        $message = $listings->count() . ' ' . str('listing')->plural($listings->count())
            . ($q !== '' ? " matching \"{$q}\"" : '')
            . ($category ? " in {$category}" : '') . '.';

        return $this->ok(ListingResource::collection($listings), $message);
    }

    /** GET /api/listings/{id} */
    public function show(Request $request, Listing $listing)
    {
        $listing->load('user');
        abort_unless($listing->visibleTo($request->user()), 404);

        return $this->ok(new ListingResource($listing));
    }

    /** POST /api/listings/scan  (A: photo -> OCR.space -> Google Books suggestions) */
    public function scan(Request $request, ScanBook $scanBook)
    {
        $request->validate(ScanBook::rules(), ScanBook::messages());

        $result = $scanBook->handle($request->file('image'));
        $body = $result['body'];
        $message = $body['message'];
        unset($body['message']);

        return $result['status'] === 200
            ? $this->ok($body, $message)
            : $this->fail($message, $result['status'], $body);
    }

    /**
     * POST /api/listings  (A: Create)
     * If an ISBN is given, missing details are filled by the book lookup
     * (books table -> Google Books -> Open Library). The book is kept in the catalogue.
     */
    public function store(ListingRequest $request, BookLookupService $lookup)
    {
        $data = $request->listingData();
        $source = $request->input('book_source');
        $bookId = $request->integer('book_id') ?: null;

        if (! empty($data['isbn'])) {
            $match = $lookup->lookup($data['isbn'])['matches'][0] ?? null;
            if ($match) {
                $data = $this->fillFrom($data, $match, overwrite: false);
                $source ??= $match['source_key'];
                $bookId ??= $match['book_id'] ?? null;
            }
        }
        if (empty($data['title'])) {
            return $this->fail('We could not find this ISBN. Please send the title.', 422, [
                'errors' => ['title' => ['The title field is required.']],
            ]);
        }

        $book = $lookup->remember($data, $source, $bookId);
        $listing = $request->user()->listings()->create($data + ['book_id' => $book?->id]);

        return $this->ok(new ListingResource($listing->load('user')), 'Listing created.', 201);
    }

    /**
     * PUT /api/listings/{id}  (B: Update)
     * Send "sync": true to refresh the book details from Google Books using the ISBN.
     */
    public function update(ListingRequest $request, Listing $listing, BooksService $books)
    {
        Gate::authorize('update', $listing);

        $data = $request->listingData();
        if ($listing->hasActiveOrder()) {
            unset($data['status']);
        }

        if ($request->boolean('sync')) {
            $isbn = $data['isbn'] ?? $listing->isbn;
            if (! $isbn) {
                return $this->fail('This listing has no ISBN, so it cannot be synced with Google Books.', 422);
            }
            $books->forget($isbn);
            $book = $books->findByIsbn($isbn);
            if ($book) {
                $data = $this->fillFrom(['isbn' => $isbn] + $data, $book, overwrite: true);
            }
        }

        $listing->update($data);

        return $this->ok(new ListingResource($listing->fresh()->load('user')), 'Listing updated.');
    }

    /** DELETE /api/listings/{id}  (B: Delete; clears the cached lookup, and the seller's photo via Listing::deleted) */
    public function destroy(Listing $listing, BooksService $books)
    {
        Gate::authorize('delete', $listing);

        if ($listing->hasActiveOrder()) {
            return $this->fail('This listing has an active booking and cannot be deleted. Cancel the booking first.', 409);
        }

        if ($listing->isbn) {
            $books->forget($listing->isbn);
        }
        $listing->delete();

        return $this->ok(null, 'Listing deleted.');
    }

    private function fillFrom(array $data, array $book, bool $overwrite): array
    {
        foreach (['title', 'author', 'edition', 'publisher'] as $field) {
            if (($overwrite || empty($data[$field])) && ! empty($book[$field])) {
                $data[$field] = $book[$field];
            }
        }

        // Cover: keep the URL/path only, and remember where it came from.
        $cover = $book['cover_path'] ?? $book['cover_url'] ?? null;
        if ($cover && ($overwrite || empty($data['cover_url']))) {
            $data['cover_url'] = $cover;
            $data['cover_source'] = $book['cover_source'] ?? null;
        }
        $data['isbn'] = $book['isbn'] ?? $data['isbn'];

        return $data;
    }
}
