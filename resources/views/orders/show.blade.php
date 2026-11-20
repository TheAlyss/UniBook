@extends('layouts.app')

@section('title', 'Order #' . $order->id)

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #map { height: 280px; }
        .ub-tl-step.cancelled { color: #b02a37; }
        .ub-tl-step.cancelled::before { background: #f1aeb5; }
        .ub-tl-step.cancelled .ub-tl-dot { background: #dc3545; border-color: #dc3545; color: #fff; }
        .ub-status { font-size: .8rem; padding: .35rem .8rem; }
        .ub-status.cancelled { background: #fde8ea; color: #b02a37; }
        .ub-party { display: flex; align-items: center; gap: .7rem; }
        .ub-summary-book .ub-cover { width: 56px; flex: 0 0 56px; }
    </style>
@endpush

@php
    $isBuyer = auth()->id() === $order->buyer_id;
    $isSeller = auth()->id() === $order->seller_id;
    $meetup = $order->meetup;
    $cover = $order->listing?->coverImageUrl();
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

    // Timeline: Booked -> Meetup -> Completed -> Rated (or Booked -> Cancelled)
    $cancelled = $order->status === 'cancelled';
    $done = [
        'booked' => true,
        'meetup' => (bool) $meetup,
        'completed' => $order->status === 'completed',
        'rated' => (bool) $order->rating,
    ];
    $current = match (true) {
        $cancelled => null,
        ! $done['meetup'] => 'meetup',
        ! $done['completed'] => 'completed',
        ! $done['rated'] => 'rated',
        default => null,
    };
    $statusClass = ['pending' => 'ub-match-medium', 'completed' => 'ub-match-high', 'cancelled' => 'cancelled'][$order->status] ?? 'ub-badge-cat';

    $nextStep = match (true) {
        $cancelled => 'This order was cancelled' . ($order->cancelled_at ? ' on ' . $order->cancelled_at->format('j M Y') : '') . '. The book is available again.',
        $order->status === 'pending' && $isSeller => 'Meet the buyer, receive RM' . number_format((float) $order->amount, 2) . ' in cash, then mark the order as completed.',
        $order->status === 'pending' => 'Bring RM' . number_format((float) $order->amount, 2) . ' in cash to the meetup. The seller marks the order completed after you meet.',
        ! $order->rating && $isBuyer => 'Completed. Please rate the seller below.',
        ! $order->rating => 'Completed. Waiting for the buyer to rate you.',
        default => 'Completed and rated. Thanks for using UniBook!',
    };
@endphp

@section('content')
<div class="container py-4">
    <a href="{{ route('orders.index') }}" class="small fw-semibold text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>My Orders
    </a>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-2 mb-3">
        <div>
            <h1 class="h3 fw-bold mb-1">Order #{{ $order->id }}</h1>
            <div class="ub-muted">Booked {{ $order->created_at->format('j M Y, g:i A') }} · You are the {{ $isSeller ? 'seller' : 'buyer' }}</div>
        </div>
        <span class="ub-badge ub-status {{ $statusClass }}">{{ $order->statusLabel() }}</span>
    </div>

    {{-- Order status timeline --}}
    <div class="ub-card p-3 p-md-4 mb-4">
        <h2 class="ub-section-title mb-3"><i class="bi bi-receipt text-primary me-2"></i>Order status</h2>
        <div class="ub-timeline mb-3">
            <div class="ub-tl-step done"><div class="ub-tl-dot"><i class="bi bi-bag-check"></i></div>Booked</div>
            @if ($cancelled)
                <div class="ub-tl-step cancelled"><div class="ub-tl-dot"><i class="bi bi-x-lg"></i></div>Cancelled</div>
            @else
                @foreach (['meetup' => ['bi-geo-alt', 'Meetup set'], 'completed' => ['bi-check2', 'Completed'], 'rated' => ['bi-star', 'Rated']] as $key => [$icon, $label])
                    <div class="ub-tl-step {{ $done[$key] ? 'done' : '' }} {{ $current === $key ? 'current' : '' }}">
                        <div class="ub-tl-dot"><i class="bi {{ $icon }}"></i></div>{{ $label }}
                    </div>
                @endforeach
            @endif
        </div>
        <div class="small ub-muted text-center">{{ $nextStep }}</div>
    </div>

    <div class="row g-4">
        {{-- LEFT: meetup --}}
        <div class="col-lg-7">
            <div class="ub-card p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="ub-section-title"><i class="bi bi-geo-alt text-primary me-2"></i>Meetup on campus</h2>
                    <span class="ub-badge ub-badge-source"><i class="bi bi-map"></i>OpenStreetMap</span>
                </div>
                @if ($meetup)
                    <div class="ub-map mb-3"><div id="map"></div></div>
                    <dl class="ub-spec mb-2">
                        <dt>Place</dt><dd>{{ $meetup->name }}</dd>
                        <dt>Time</dt><dd>{{ $meetup->meetup_time->format('D, j M Y, g:i A') }}</dd>
                        @if ($meetup->distanceLabel())
                            <dt>From buyer</dt><dd>{{ $meetup->distanceLabel() }}@if ($meetup->durationLabel()) · about {{ $meetup->durationLabel() }} by road @endif</dd>
                        @endif
                        @if ($order->note)
                            <dt>Buyer's note</dt><dd class="fw-normal fst-italic">"{{ $order->note }}"</dd>
                        @endif
                    </dl>
                    <a class="small fw-semibold" target="_blank" rel="noopener"
                       href="https://www.openstreetmap.org/?mlat={{ $meetup->lat }}&mlon={{ $meetup->lng }}#map=18/{{ $meetup->lat }}/{{ $meetup->lng }}">
                        Open in OpenStreetMap <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                @else
                    <p class="ub-muted small mb-0">No meetup has been set for this order yet.</p>
                @endif
            </div>
        </div>

        {{-- RIGHT: summary + actions --}}
        <div class="col-lg-5">
            <div class="ub-card p-3 p-md-4 mb-3">
                <div class="d-flex gap-3 align-items-center ub-summary-book mb-3">
                    @if ($cover)
                        <img class="ub-cover" src="{{ $cover }}" alt="Book cover">
                    @else
                        <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book"></i></div></div>
                    @endif
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold">
                            @if ($order->listing)
                                <a href="{{ route('books.show', $order->listing) }}" class="text-decoration-none text-reset">{{ $order->listing_title }}</a>
                            @else
                                {{ $order->listing_title }}
                            @endif
                        </div>
                        <div class="ub-price mt-1"><small>RM</small>{{ number_format((float) $order->amount, 2) }}</div>
                    </div>
                </div>
                <div class="ub-pay-note mb-3"><i class="bi bi-cash-coin"></i>Cash on meetup</div>

                <div class="d-grid gap-2">
                    <div class="ub-party">
                        <span class="ub-avatar">{{ $initials($order->buyer->name) }}</span>
                        <div><div class="small ub-muted">Buyer</div><div class="fw-semibold">{{ $order->buyer->name }}</div></div>
                    </div>
                    <div class="ub-party">
                        <span class="ub-avatar">{{ $initials($order->seller->name) }}</span>
                        <div>
                            <div class="small ub-muted">Seller</div>
                            <div class="fw-semibold">
                                {{ $order->seller->name }}
                                @if ($sellerRating)
                                    <span class="small fw-normal ub-muted ms-1"><i class="bi bi-star-fill" style="color: var(--ub-accent);"></i> {{ number_format($sellerRating['average'], 1) }} ({{ $sellerRating['count'] }})</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions depend on the status and who is looking --}}
            <div class="ub-card p-3 p-md-4">
                @if ($order->status === 'pending')
                    @if ($isSeller)
                        <form method="POST" action="{{ route('orders.complete', $order) }}" class="mb-2"
                              onsubmit="return confirm('Confirm that you met the buyer and received the cash?');">
                            @csrf
                            <button class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1"></i>Mark as Completed</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?');">
                        @csrf
                        <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>Cancel Order</button>
                    </form>

                @elseif ($order->status === 'completed')
                    <div class="small mb-2"><i class="bi bi-check-circle-fill" style="color: var(--ub-success);"></i> Completed on {{ $order->completed_at->format('j M Y') }}.</div>

                    @if ($order->rating)
                        <div class="fw-bold mb-1">{{ $isBuyer ? 'Your rating' : 'Buyer\'s rating' }}</div>
                        <div class="ub-stars" aria-label="{{ $order->rating }} out of 5 stars">
                            @for ($i = 1; $i <= 5; $i++)<button type="button" disabled class="{{ $i <= $order->rating ? 'on' : '' }}"><i class="bi bi-star-fill"></i></button>@endfor
                        </div>
                        @if ($order->review)
                            <p class="small fst-italic mt-2 mb-0">"{{ $order->review }}"</p>
                        @endif
                    @elseif ($isBuyer)
                        <form method="POST" action="{{ route('orders.rate', $order) }}" novalidate id="rate-form">
                            @csrf
                            <div class="fw-bold mb-1">Rate seller</div>
                            <input type="hidden" name="rating" id="rating" value="{{ old('rating') }}">
                            <div class="ub-stars" id="stars" role="radiogroup" aria-label="Rating">
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" data-star="{{ $i }}" role="radio" aria-checked="false" aria-label="{{ $i }} {{ Str::plural('star', $i) }}" class="{{ (int) old('rating') >= $i ? 'on' : '' }}"><i class="bi bi-star-fill"></i></button>
                                @endfor
                            </div>
                            <div class="small ub-muted mt-1 mb-2" id="rate-text">How was your meetup with {{ $order->seller->name }}?</div>
                            @error('rating') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            <textarea name="review" rows="2" maxlength="500" class="form-control mb-2" placeholder="Short review (optional)">{{ old('review') }}</textarea>
                            <button class="btn btn-primary w-100"><i class="bi bi-star me-1"></i>Rate Seller</button>
                        </form>
                    @else
                        <p class="small ub-muted mb-0">Waiting for the buyer to rate you.</p>
                    @endif

                @else
                    <p class="small ub-muted mb-0">This order was cancelled. Nothing else to do.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @if ($meetup)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
        (function () {
            const meetup = [{{ $meetup->lat }}, {{ $meetup->lng }}];
            const map = L.map('map').setView(meetup, 17);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            L.marker(meetup).addTo(map).bindPopup(@json($meetup->name)).openPopup();
        })();
        </script>
    @endif
    <script>
    // Star rating: clicking a star sets the hidden "rating" field.
    (function () {
        const stars = document.getElementById('stars');
        if (!stars) return;
        const input = document.getElementById('rating');
        const text = document.getElementById('rate-text');
        function paint(value) {
            stars.querySelectorAll('[data-star]').forEach(function (b) {
                const n = Number(b.dataset.star);
                b.classList.toggle('on', n <= value);
                b.setAttribute('aria-checked', n === value ? 'true' : 'false');
            });
        }
        stars.addEventListener('click', function (e) {
            const b = e.target.closest('[data-star]');
            if (!b) return;
            input.value = b.dataset.star;
            paint(Number(b.dataset.star));
            text.textContent = 'You chose ' + b.dataset.star + ' out of 5.';
        });
        paint(Number(input.value) || 0);
    })();
    </script>
@endpush
