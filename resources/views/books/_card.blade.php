{{-- One book card (home page grid and "More books" rows).
     $book: Listing (with user), $rating: seller's average rating or null --}}
@php
    $conditionClass = ['Like new' => 'like-new', 'Good' => 'good', 'Fair' => 'fair'][$book->condition] ?? 'good';
    $conditionIcon = ['Like new' => 'bi-patch-check-fill', 'Good' => 'bi-hand-thumbs-up-fill', 'Fair' => 'bi-info-circle-fill'][$book->condition] ?? 'bi-info-circle-fill';
    $cover = $book->coverImageUrl();
@endphp
<a class="ub-book-card" href="{{ route('books.show', $book->id) }}">
    <div class="ub-cover-wrap">
        @if ($cover)
            <img class="ub-cover" src="{{ $cover }}" alt="{{ $book->title }} cover" loading="lazy">
        @else
            <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book"></i>No cover</div></div>
        @endif
        <span class="ub-badge ub-badge-cond {{ $conditionClass }}"><i class="bi {{ $conditionIcon }}"></i>{{ $book->condition }}</span>
    </div>
    <div class="ub-body">
        <div class="ub-price"><small>RM</small>{{ number_format((float) $book->price, 2) }}</div>
        <div class="ub-book-title">{{ $book->title }}</div>
        <div class="ub-book-author">{{ $book->author ?: "\u{00A0}" }}</div>
        <div class="d-flex flex-wrap gap-1">
            @if ($book->course_code)
                <span class="ub-badge ub-badge-course" title="Course code">{{ $book->course_code }}</span>
            @endif
            <span class="ub-badge ub-badge-cat">{{ $book->category }}</span>
        </div>
        <div class="ub-seller">
            <i class="bi bi-person-circle"></i>{{ $book->user->name }}
            <span class="ms-auto">
                @if ($rating)
                    <i class="bi bi-star-fill"></i> {{ number_format($rating, 1) }}
                @else
                    <span class="small">New seller</span>
                @endif
            </span>
        </div>
    </div>
</a>
