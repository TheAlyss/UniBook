<?php

namespace App\Http\Controllers;

use App\Actions\ScanBook;
use App\Http\Requests\ListingRequest;
use App\Models\Listing;
use App\Services\BookLookupService;
use App\Services\BooksService;
use App\Support\Covers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Module A (Scan & Listing) + seller side of Module B (Catalogue) for the website.
 */
class ListingController extends Controller
{
    /** Seller's own listings (all statuses). */
    public function mine(Request $request)
    {
        return view('listings.mine', [
            'listings' => $request->user()->listings()->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('listings.create', [
            'conditions' => Listing::CONDITIONS,
            'courses' => Listing::courseCodes(),
        ]);
    }

    /**
     * Look up a book by ISBN or title: books table -> Google Books -> Open Library.
     * Only real matches are returned in "results"; unrelated hits go to "other_results".
     */
    public function lookup(Request $request, BookLookupService $lookup)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ], [
            'q.required' => 'Please enter an ISBN or a book title.',
            'q.min' => 'Please enter at least 2 characters.',
        ]);

        $found = $lookup->lookup($validated['q']);
        $count = count($found['matches']);

        return response()->json([
            'message' => $count === 0
                ? 'No matching book found. Please fill in the details manually.'
                : $count . ' ' . Str::plural('match', $count) . ' found. Choose the correct book.',
            'isbn' => $found['isbn'],
            'source' => $found['source'],
            'results' => $found['matches'],
            'other_results' => $found['others'],
        ], $count === 0 ? 404 : 200);
    }

    /** Photo of the cover / back cover -> OCR.space -> book lookup (or auto-fill from the text). */
    public function scan(Request $request, ScanBook $scanBook)
    {
        $request->validate(ScanBook::rules(), ScanBook::messages());

        $result = $scanBook->handle($request->file('image'));

        // Remember which temporary scans belong to this seller, so only they can discard them (Reset).
        if ($path = $result['body']['photo']['path'] ?? null) {
            $request->session()->push('my_scans', basename($path));
        }

        return response()->json($result['body'], $result['status']);
    }

    /** Reset button: the seller cancels, so their temporary scan is deleted straight away. */
    public function discardScan(Request $request, string $name)
    {
        $mine = $request->session()->get('my_scans', []);
        abort_unless(in_array($name, $mine, true), 404);

        Covers::discardTempPhoto('scan/' . $name);
        $request->session()->put('my_scans', array_values(array_diff($mine, [$name])));

        return response()->noContent();
    }

    /** Shows a temporary scan (kept outside public/ until the listing is posted). */
    public function scanPhoto(string $name)
    {
        abort_unless(\Illuminate\Support\Facades\Storage::disk('scans')->exists($name), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::disk('scans')->path($name), ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Edition / publisher for the book the seller picked (search results often lack them). */
    public function bookDetails(Request $request, BooksService $books)
    {
        $v = $request->validate([
            'google_id' => ['nullable', 'string', 'max:40'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($books->details($v['google_id'] ?? null, $v['isbn'] ?? null, $v['title'] ?? null));
    }

    public function store(ListingRequest $request, BookLookupService $lookup)
    {
        $data = $request->listingData();
        // Keep the book in our catalogue (source = manual when typed in or auto-filled from OCR).
        $book = $lookup->remember($data, $request->input('book_source'), $request->integer('book_id') ?: null);
        $listing = $request->user()->listings()->create($data + ['book_id' => $book?->id]);

        return redirect()
            ->route('books.show', $listing)
            ->with('success', 'Your listing has been posted. Share it with your course group on WhatsApp!');
    }

    public function edit(Listing $listing)
    {
        Gate::authorize('update', $listing);

        return view('listings.edit', [
            'listing' => $listing,
            'conditions' => Listing::CONDITIONS,
            'statuses' => array_intersect_key(Listing::STATUSES, array_flip(['available', 'sold'])),
            'locked' => $listing->hasActiveOrder(),
            'courses' => Listing::courseCodes(),
        ]);
    }

    public function update(ListingRequest $request, Listing $listing)
    {
        Gate::authorize('update', $listing);

        $data = $request->listingData();
        if ($listing->hasActiveOrder()) {
            unset($data['status']); // stays "reserved" until the order is completed or cancelled
        }
        $listing->update($data);

        return redirect()
            ->route('listings.mine')
            ->with('success', "\"{$listing->title}\" has been updated.");
    }

    public function destroy(Listing $listing, BooksService $books)
    {
        Gate::authorize('delete', $listing);

        if ($listing->hasActiveOrder()) {
            return back()->with('error', 'This listing has an active booking and cannot be deleted. Cancel the booking first.');
        }

        $title = $listing->title;
        if ($listing->isbn) {
            $books->forget($listing->isbn);
        }
        $listing->delete();

        return redirect()
            ->route('listings.mine')
            ->with('success', "\"{$title}\" has been deleted.");
    }
}
