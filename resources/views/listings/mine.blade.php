@extends('layouts.app')

@section('title', 'My Listings')

@push('head')
    <style>
        .ub-listing { min-width: 0; display: flex; gap: .9rem; align-items: center; padding: .9rem; }
        .ub-listing .ub-cover { width: 56px; flex: 0 0 56px; }
    </style>
@endpush

@section('content')
@php $statusClass = ['available' => 'ub-match-high', 'reserved' => 'ub-match-medium', 'sold' => 'ub-badge-cat']; @endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1">My Listings</h1>
            <div class="ub-muted">{{ $listings->count() }} {{ Str::plural('listing', $listings->count()) }}. Sold listings are hidden from search.</div>
        </div>
        <a href="{{ route('listings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Sell a Book</a>
    </div>

    @if ($listings->isEmpty())
        <div class="ub-card ub-empty">
            <div class="ub-empty-icon"><i class="bi bi-journal-plus"></i></div>
            <h2 class="h5 fw-bold">You haven't listed any books yet</h2>
            <p class="ub-muted mb-3">Scan a book cover and we'll fill in most of the details for you.</p>
            <a href="{{ route('listings.create') }}" class="btn btn-primary"><i class="bi bi-camera me-1"></i>Sell your first book</a>
        </div>
    @else
        <div class="d-grid gap-3">
            @foreach ($listings as $listing)
                @php $cover = $listing->coverImageUrl(); @endphp
                <div class="ub-card ub-listing">
                    <a href="{{ route('books.show', $listing) }}" class="flex-shrink-0">
                        @if ($cover)
                            <img class="ub-cover" src="{{ $cover }}" alt="" loading="lazy">
                        @else
                            <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book"></i></div></div>
                        @endif
                    </a>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                            <span class="ub-badge {{ $statusClass[$listing->status] ?? 'ub-badge-cat' }}">{{ $listing->statusLabel() }}</span>
                            @if ($listing->course_code)<span class="ub-badge ub-badge-course">{{ $listing->course_code }}</span>@endif
                            <span class="ub-badge ub-badge-cat">{{ $listing->category }}</span>
                        </div>
                        <a href="{{ route('books.show', $listing) }}" class="fw-bold text-decoration-none text-reset d-block text-truncate">{{ $listing->title }}</a>
                        <div class="small ub-muted text-truncate">
                            @if ($listing->author){{ $listing->author }} · @endif{{ $listing->condition }} · posted {{ $listing->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="ub-price mb-2" style="font-size: 1.1rem;"><small>RM</small>{{ number_format((float) $listing->price, 2) }}</div>
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('listings.edit', $listing) }}" class="btn btn-sm btn-soft" title="Edit"><i class="bi bi-pencil"></i><span class="d-none d-md-inline ms-1">Edit</span></a>
                            <form method="POST" action="{{ route('listings.destroy', $listing) }}"
                                  onsubmit="return confirm('Delete this listing? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i><span class="d-none d-md-inline ms-1">Delete</span></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
