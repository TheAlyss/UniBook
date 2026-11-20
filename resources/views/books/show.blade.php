@extends('layouts.app')

@section('title', $book->title)

@push('head')
    <style>
        /* Phone: a full-width 2:3 cover would fill the whole screen. */
        @media (max-width: 767.98px) { .ub-detail-cover { max-width: 220px; } }
    </style>
@endpush

@section('content')
@php
    $cover = $book->coverImageUrl();
    $conditionIcon = ['Like new' => 'bi-patch-check-fill', 'Good' => 'bi-hand-thumbs-up-fill', 'Fair' => 'bi-info-circle-fill'][$book->condition] ?? 'bi-info-circle-fill';
    $seller = $book->user;
    $sellerInitials = collect(preg_split('/\s+/', trim($seller->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

<div class="container py-4">
    <a href="{{ route('home') }}" class="small fw-semibold text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Back to listings
    </a>

    <div class="row g-4 mt-1">
        {{-- Cover --}}
        <div class="col-md-5 col-lg-4">
            <div class="ub-card p-3">
                <div class="ub-detail-cover">
                    @if ($cover)
                        <img class="ub-cover" src="{{ $cover }}" alt="{{ $book->title }} cover">
                    @else
                        <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book"></i>No cover</div></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="col-md-7 col-lg-5">
            <div class="d-flex flex-wrap gap-2 mb-2">
                @if ($book->course_code)
                    <a href="{{ route('home', ['q' => $book->course_code]) }}" class="ub-badge ub-badge-course text-decoration-none" title="More books for this course">{{ $book->course_code }}</a>
                @endif
                <a href="{{ route('home', ['category' => $book->category]) }}" class="ub-badge ub-badge-cat text-decoration-none">{{ $book->category }}</a>
                <span class="ub-badge ub-badge-cat"><i class="bi {{ $conditionIcon }}"></i>{{ $book->condition }}</span>
            </div>
            <h1 class="h3 fw-bold mb-1">{{ $book->title }}</h1>
            <div class="ub-muted mb-3">{{ $book->author ? 'by ' . $book->author : 'Author not listed' }}</div>

            <div class="ub-card p-3 mb-3">
                <dl class="ub-spec mb-0">
                    <dt>Condition</dt><dd>{{ $book->condition }}</dd>
                    <dt>Category</dt><dd>{{ $book->category }}</dd>
                    <dt>Course</dt><dd>{{ $book->course_code ?: '–' }}</dd>
                    <dt>ISBN</dt><dd>{{ $book->isbn ?: 'Not available' }}</dd>
                    <dt>Publisher</dt><dd>{{ $book->publisher ?: '–' }}</dd>
                    <dt>Edition</dt><dd>{{ $book->edition ?: '–' }}</dd>
                    <dt>Posted</dt><dd>{{ $book->created_at->diffForHumans() }}</dd>
                </dl>
            </div>

            <div class="ub-card p-3 mb-3">
                <div class="ub-muted small fw-semibold mb-1">Notes from the seller</div>
                <div class="{{ $book->description ? '' : 'ub-muted fst-italic' }}" style="white-space: pre-line;">{{ $book->description ?: 'No notes from the seller.' }}</div>
            </div>

            <div class="ub-card p-3 d-flex align-items-center gap-3">
                <span class="ub-avatar" style="width: 48px; height: 48px;">{{ $sellerInitials }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold">{{ $seller->name }}</div>
                    <div class="small ub-muted">
                        @if ($sellerRating)
                            <i class="bi bi-star-fill" style="color: var(--ub-accent);"></i>
                            {{ number_format($sellerRating['average'], 1) }} ({{ $sellerRating['count'] }} {{ Str::plural('rating', $sellerRating['count']) }})
                        @else
                            New seller, no ratings yet
                        @endif
                        · {{ $sellerSold }} {{ Str::plural('book', $sellerSold) }} sold
                    </div>
                </div>
            </div>
        </div>

        {{-- Price box (stays in view on wide screens) --}}
        <div class="col-lg-3">
            <div class="ub-card p-3 ub-sticky ub-price-box">
                <div class="ub-muted small fw-semibold">Price</div>
                <div class="ub-price mb-2"><small>RM</small>{{ number_format((float) $book->price, 2) }}</div>
                <div class="ub-pay-note mb-3"><i class="bi bi-cash-coin"></i>Cash on meetup</div>

                @if ($book->status !== 'available')
                    <div class="alert alert-secondary py-2 small mb-3" style="border-radius: 12px;">
                        This book is <strong>{{ strtolower($book->statusLabel()) }}</strong> and no longer appears in search.
                    </div>
                @endif

                @can('update', $book)
                    <a href="{{ route('listings.edit', $book) }}" class="btn btn-primary w-100 mb-2"><i class="bi bi-pencil me-1"></i>Edit Listing</a>
                @else
                    @if ($book->status === 'available')
                        <a href="{{ route('orders.create', $book) }}" class="btn btn-primary w-100 mb-2"><i class="bi bi-bag-check me-1"></i>Book Now</a>
                    @else
                        <button type="button" class="btn btn-primary w-100 mb-2" disabled><i class="bi bi-bag-x me-1"></i>Not Available</button>
                    @endif
                @endcan
                <a class="btn btn-whatsapp w-100" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>Share to WhatsApp</a>

                @if ($book->status === 'available')
                    <div class="small ub-muted mt-2 text-center">Choose a meetup point on campus and pay the seller in cash when you meet.</div>
                @endif
            </div>
        </div>
    </div>

    @if (count($related) > 0)
        <div class="mt-5">
            <h2 class="ub-section-title mb-3">More books {{ $relatedLabel }}</h2>
            <div class="ub-row-scroll">
                @foreach ($related as $item)
                    @include('books._card', ['book' => $item, 'rating' => $relatedRatings[$item->user_id] ?? null])
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
