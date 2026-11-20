@extends('layouts.app')

@section('title', 'Find Books')

@section('content')
@php
    $filtered = $q !== '' || $category !== '';
    $chipIcons = ['Textbook' => 'bi-journal-text', 'Reference' => 'bi-bookmark-star', 'Novel' => 'bi-book', 'Religious' => 'bi-moon-stars', 'Others' => 'bi-three-dots'];
    // Links keep the other filters (search text, category, sort).
    $link = fn (array $change) => route('home', array_filter(array_merge(
        ['q' => $q, 'category' => $category, 'sort' => $sort === 'newest' ? '' : $sort],
        $change
    )));
@endphp

<section class="ub-hero">
    <div class="container position-relative" style="z-index: 1;">
        <p class="mb-1 fw-semibold" style="opacity: .8;"><i class="bi bi-geo-alt-fill me-1"></i>UiTM Perlis, Arau Campus</p>
        <h1 class="mb-3">Pre-loved books from <span>fellow students</span></h1>

        <form method="GET" action="{{ route('home') }}" class="ub-search mb-3" role="search">
            <i class="bi bi-search ub-muted"></i>
            <input type="search" name="q" value="{{ $q }}" maxlength="100"
                   placeholder="Search title, author, ISBN or course code" aria-label="Search books">
            @if ($category !== '')
                <input type="hidden" name="category" value="{{ $category }}">
            @endif
            @if ($sort !== 'newest')
                <input type="hidden" name="sort" value="{{ $sort }}">
            @endif
            <button class="btn btn-primary px-4" type="submit">Search</button>
        </form>

        <div class="ub-chips" aria-label="Categories">
            <a href="{{ $link(['category' => '']) }}" class="ub-chip {{ $category === '' ? 'active' : '' }}"><i class="bi bi-grid"></i>All</a>
            @foreach ($categories as $option)
                <a href="{{ $link(['category' => $option]) }}" class="ub-chip {{ $category === $option ? 'active' : '' }}">
                    <i class="bi {{ $chipIcons[$option] ?? 'bi-book' }}"></i>{{ $option }}
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="container py-4">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h2 class="ub-section-title">{{ $filtered ? 'Search results' : 'Latest listings' }}</h2>
            <div class="ub-muted small">
                {{ count($books) }} {{ Str::plural('book', count($books)) }} available
                @if ($q !== '') matching <strong>"{{ $q }}"</strong> @endif
                @if ($category !== '') in <strong>{{ $category }}</strong> @endif
                @if ($filtered)
                    · <a href="{{ route('home') }}" class="fw-semibold">Clear search</a>
                @endif
            </div>
        </div>
        @if (count($books) > 1)
            <form method="GET" action="{{ route('home') }}">
                @if ($q !== '') <input type="hidden" name="q" value="{{ $q }}"> @endif
                @if ($category !== '') <input type="hidden" name="category" value="{{ $category }}"> @endif
                <select name="sort" class="form-select form-select-sm w-auto" aria-label="Sort" onchange="this.form.submit()">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (count($books) === 0)
        <div class="ub-card ub-empty">
            <div class="ub-empty-icon"><i class="bi {{ $filtered ? 'bi-search' : 'bi-book' }}"></i></div>
            @if ($filtered)
                <h3 class="h5 fw-bold">No books match your search</h3>
                <p class="ub-muted mb-3">Try another title, author, ISBN or course code, or check a different category.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a class="btn btn-soft" href="{{ route('home') }}"><i class="bi bi-x-circle me-1"></i>Clear search</a>
                    <a class="btn btn-primary" href="{{ route('listings.create') }}"><i class="bi bi-plus-lg me-1"></i>Sell this book instead</a>
                </div>
            @else
                <h3 class="h5 fw-bold">No books for sale yet</h3>
                <p class="ub-muted mb-3">Be the first to list a book for your fellow students.</p>
                <a class="btn btn-primary" href="{{ route('listings.create') }}"><i class="bi bi-plus-lg me-1"></i>Sell a book</a>
            @endif
        </div>
    @else
        <div class="row g-3 g-lg-4">
            @foreach ($books as $book)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('books._card', ['book' => $book, 'rating' => $ratings[$book->user_id] ?? null])
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    // Phone: the chip row scrolls sideways, so bring the selected category into view.
    (function () {
        const chip = document.querySelector('.ub-chip.active');
        const row = chip && chip.parentElement;
        if (row && row.scrollWidth > row.clientWidth) {
            row.scrollLeft = chip.offsetLeft - (row.clientWidth - chip.offsetWidth) / 2;
        }
    })();
</script>
@endpush
