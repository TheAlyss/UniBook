@extends('layouts.app')

@section('title', 'My Orders')

@push('head')
    <style>
        .ub-tabs { display: inline-flex; gap: .35rem; background: var(--ub-surface); border: 1px solid var(--ub-border); border-radius: 999px; padding: 4px; }
        .ub-tabs .nav-link { border-radius: 999px; color: var(--ub-muted); font-weight: 600; padding: .45rem 1rem; }
        .ub-tabs .nav-link.active { background: var(--ub-primary); color: #fff; }
        .ub-tabs .count { font-size: .72rem; background: rgba(0,0,0,.08); border-radius: 999px; padding: .05rem .45rem; margin-left: .25rem; }
        .ub-tabs .nav-link.active .count { background: rgba(255,255,255,.25); }
        .ub-order { min-width: 0; display: flex; gap: .9rem; align-items: center; padding: .9rem; text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s; }
        .ub-order:hover { box-shadow: var(--ub-shadow-hover); transform: translateY(-2px); color: inherit; }
        .ub-order .ub-cover { width: 56px; flex: 0 0 56px; }
        .ub-status.cancelled { background: #fde8ea; color: #b02a37; }
    </style>
@endpush

@section('content')
@php
    $tab = request('tab') === 'selling' || ($purchases->isEmpty() && $sales->isNotEmpty() && ! request('tab')) ? 'selling' : 'buying';
    $statusClass = ['pending' => 'ub-match-medium', 'completed' => 'ub-match-high', 'cancelled' => 'cancelled'];
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">My Orders</h1>
            <div class="ub-muted">Books you booked, and books other students booked from you.</div>
        </div>
        <ul class="nav ub-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $tab === 'buying' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-buying" type="button" role="tab">
                    <i class="bi bi-bag me-1"></i>Buying<span class="count">{{ $purchases->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $tab === 'selling' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-selling" type="button" role="tab">
                    <i class="bi bi-shop me-1"></i>Selling<span class="count">{{ $sales->count() }}</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        @foreach (['buying' => [$purchases, 'seller', 'Seller'], 'selling' => [$sales, 'buyer', 'Buyer']] as $key => [$orders, $other, $otherLabel])
            <div class="tab-pane fade {{ $tab === $key ? 'show active' : '' }}" id="tab-{{ $key }}" role="tabpanel">
                @if ($orders->isEmpty())
                    <div class="ub-card ub-empty">
                        <div class="ub-empty-icon"><i class="bi {{ $key === 'buying' ? 'bi-bag' : 'bi-shop' }}"></i></div>
                        @if ($key === 'buying')
                            <h2 class="h5 fw-bold">No bookings yet</h2>
                            <p class="ub-muted mb-3">Find a book from a fellow student and book a meetup on campus.</p>
                            <a class="btn btn-primary" href="{{ route('home') }}"><i class="bi bi-search me-1"></i>Find books</a>
                        @else
                            <h2 class="h5 fw-bold">Nobody has booked your books yet</h2>
                            <p class="ub-muted mb-3">Share your listings on WhatsApp to reach more buyers.</p>
                            <a class="btn btn-primary" href="{{ route('listings.mine') }}"><i class="bi bi-collection me-1"></i>My listings</a>
                        @endif
                    </div>
                @else
                    <div class="d-grid gap-3">
                        @foreach ($orders as $order)
                            @php $cover = $order->listing?->coverImageUrl(); @endphp
                            <a class="ub-card ub-order" href="{{ route('orders.show', $order) }}">
                                @if ($cover)
                                    <img class="ub-cover" src="{{ $cover }}" alt="" loading="lazy">
                                @else
                                    <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book"></i></div></div>
                                @endif
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="ub-badge ub-status {{ $statusClass[$order->status] ?? 'ub-badge-cat' }}">{{ $order->statusLabel() }}</span>
                                        <span class="small ub-muted">Order #{{ $order->id }} · {{ $order->created_at->format('j M Y') }}</span>
                                    </div>
                                    <div class="fw-bold text-truncate">{{ $order->listing_title }}</div>
                                    <div class="small ub-muted text-truncate">
                                        <i class="bi bi-person"></i> {{ $otherLabel }}: {{ $order->$other->name }}
                                        @if ($order->meetup)
                                            <span class="d-none d-sm-inline">·</span><br class="d-sm-none">
                                            <i class="bi bi-geo-alt"></i> {{ $order->meetup->name }}, {{ $order->meetup->meetup_time->format('D j M, g:i A') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="ub-price" style="font-size: 1.1rem;"><small>RM</small>{{ number_format((float) $order->amount, 2) }}</div>
                                    <i class="bi bi-chevron-right ub-muted"></i>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
