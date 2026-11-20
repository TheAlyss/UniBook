@extends('layouts.app')

@section('title', 'Catalogue · Admin')

@section('content')
@php $sourceLabels = ['google_books' => 'Google Books', 'open_library' => 'Open Library', 'manual' => 'Manual']; @endphp
<div class="container py-4">
    @include('admin._nav', ['title' => 'Catalogue', 'subtitle' => 'Books UniBook already knows. Sellers see these first, so fix wrong details or covers here.'])

    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control" style="max-width: 320px;" placeholder="Title, author or ISBN" aria-label="Search catalogue">
        <button class="btn btn-primary">Search</button>
        @if ($q !== '')<a href="{{ route('admin.books.index') }}" class="btn btn-link">Clear</a>@endif
    </form>

    <div class="ub-card overflow-hidden">
        <div class="table-responsive">
            <table class="table ub-admin-table">
                <thead><tr><th>Book</th><th>ISBN</th><th>Found by</th><th class="text-end">Listings</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($books as $b)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($b->coverImageUrl())
                                        <img class="ub-cover" src="{{ $b->coverImageUrl() }}" alt="" loading="lazy">
                                    @else
                                        <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book" style="font-size: 1rem;"></i></div></div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="fw-semibold">{{ $b->title }}</div>
                                        <div class="small ub-muted">{{ $b->author ?: 'Author not listed' }}@if ($b->edition) · {{ $b->edition }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td class="small text-nowrap">{{ $b->isbn ?: '–' }}</td>
                            <td><span class="ub-badge ub-badge-source">{{ $sourceLabels[$b->source] ?? $b->source }}</span></td>
                            <td class="text-end">{{ $b->listings_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.books.edit', $b) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.books.destroy', $b) }}" class="d-inline" onsubmit="return confirm('Remove this book from the catalogue? Listings keep their own details.');">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center ub-muted py-4">No books match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $books->links() }}</div>
</div>
@endsection
