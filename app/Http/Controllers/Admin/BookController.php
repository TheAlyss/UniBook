<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\BooksService;
use App\Support\Covers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin: the UniBook book catalogue (the `books` table searched first by every seller).
 * Used to fix wrong details or covers, e.g. a wrong picture served by Google Books.
 */
class BookController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $books = Book::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('author', 'like', "%{$q}%")
                ->orWhere('isbn', 'like', '%' . preg_replace('/[\s-]/', '', $q) . '%')))
            ->withCount('listings')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.books.index', compact('books', 'q'));
    }

    public function edit(Book $book)
    {
        return view('admin.books.edit', compact('book'));
    }

    public function update(Request $request, Book $book)
    {
        $request->merge(['isbn' => BooksService::normaliseIsbn((string) $request->input('isbn')) ?? ($request->filled('isbn') ? 'invalid' : null)]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:50'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_year' => ['nullable', 'digits:4'],
            'isbn' => ['nullable', 'regex:/^(97[89]\d{10}|\d{9}[\dX])$/', Rule::unique('books', 'isbn')->ignore($book->id)],
            'cover_url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                $isHttps = filter_var($value, FILTER_VALIDATE_URL) && str_starts_with($value, 'https://');
                if (! $isHttps && ! Covers::isStoredPhoto($value)) {
                    $fail('The cover must be an https image link or a photo uploaded to UniBook.');
                }
            }],
            'cover_source' => ['nullable', Rule::in(Covers::SOURCES)],
        ], [
            'isbn.regex' => 'The ISBN must be a valid ISBN-10 or ISBN-13.',
            'isbn.unique' => 'Another catalogue book already has this ISBN.',
        ]);

        if (blank($data['cover_url'] ?? null)) {
            $data['cover_source'] = null;
        }

        $book->update($data);

        return redirect()->route('admin.books.index')->with('success', "\"{$book->title}\" was updated.");
    }

    public function destroy(Book $book)
    {
        $book->delete(); // listings keep their own details; only the catalogue link is removed

        return back()->with('success', "\"{$book->title}\" was removed from the catalogue.");
    }
}
