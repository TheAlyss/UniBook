@extends('layouts.app')

@section('title', $user->name . ' · Admin')

@section('content')
@php
    $orderStatus = ['pending' => 'ub-match-medium', 'completed' => 'ub-match-high', 'cancelled' => 'ub-badge-banned'];
    $listingStatus = ['available' => 'ub-match-high', 'reserved' => 'ub-match-medium', 'sold' => 'ub-badge-cat'];
    $isSelf = $user->is(auth()->user());
    $canDelete = ! $isSelf && ! $user->isAdmin() && ! $user->hasOrders();
@endphp
<div class="container py-4">
    @include('admin._nav', ['title' => $user->name, 'subtitle' => $user->email])

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="ub-card p-3 p-md-4">
                <div class="d-flex flex-wrap gap-1 mb-3">
                    @if ($user->isAdmin())<span class="ub-badge ub-badge-admin">Admin</span>@else<span class="ub-badge ub-badge-cat">User</span>@endif
                    @if ($user->isBanned())<span class="ub-badge ub-badge-banned">Banned {{ $user->banned_at->format('j M Y') }}</span>@endif
                    @if ($isSelf)<span class="ub-badge ub-badge-course">You</span>@endif
                </div>
                <dl class="ub-spec mb-3">
                    <dt>Joined</dt><dd>{{ $user->created_at->format('j M Y') }}</dd>
                    <dt>Listings</dt><dd>{{ $user->listings->count() }}</dd>
                    <dt>Bought</dt><dd>{{ $user->purchases->count() }} {{ Str::plural('order', $user->purchases->count()) }}</dd>
                    <dt>Sold</dt><dd>{{ $user->sales->count() }} {{ Str::plural('order', $user->sales->count()) }}</dd>
                    <dt>Rating</dt><dd>{{ $rating ? number_format($rating['average'], 1) . ' (' . $rating['count'] . ')' : '–' }}</dd>
                </dl>

                <div class="d-grid gap-2">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Edit user</a>
                    @unless ($isSelf || $user->isAdmin())
                        @if ($user->isBanned())
                            <form method="POST" action="{{ route('admin.users.unban', $user) }}">@csrf
                                <button class="btn btn-soft w-100"><i class="bi bi-unlock me-1"></i>Unban</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.users.ban', $user) }}" onsubmit="return confirm('Ban {{ addslashes($user->name) }}? They will be logged out and their listings hidden.');">@csrf
                                <button class="btn btn-outline-danger w-100"><i class="bi bi-slash-circle me-1"></i>Ban user</button>
                            </form>
                        @endif
                    @endunless
                    @if ($canDelete)
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete {{ addslashes($user->name) }} and all their listings? This cannot be undone.');">@csrf @method('DELETE')
                            <button class="btn btn-outline-danger w-100"><i class="bi bi-trash me-1"></i>Delete user</button>
                        </form>
                    @elseif (! $isSelf)
                        <div class="small ub-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ $user->isAdmin() ? 'Admins cannot be banned or deleted. Remove the admin role first.' : 'This user has orders, so they cannot be deleted. Ban them instead.' }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="ub-card overflow-hidden mb-4">
                <div class="p-3"><h2 class="ub-section-title">Listings</h2></div>
                <div class="table-responsive">
                    <table class="table ub-admin-table">
                        <thead><tr><th>Book</th><th class="text-end">Price</th><th>Status</th><th>Posted</th></tr></thead>
                        <tbody>
                            @forelse ($user->listings as $l)
                                <tr>
                                    <td><a href="{{ route('books.show', $l) }}" class="text-decoration-none">{{ $l->title }}</a>
                                        @if ($l->hidden_at)<span class="ub-badge ub-badge-hidden ms-1">Hidden</span>@endif</td>
                                    <td class="text-end text-nowrap">RM{{ number_format((float) $l->price, 2) }}</td>
                                    <td><span class="ub-badge {{ $listingStatus[$l->status] ?? 'ub-badge-cat' }}">{{ $l->statusLabel() }}</span></td>
                                    <td class="small ub-muted text-nowrap">{{ $l->created_at->format('j M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center ub-muted py-3">No listings.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ub-card overflow-hidden">
                <div class="p-3"><h2 class="ub-section-title">Orders</h2></div>
                <div class="table-responsive">
                    <table class="table ub-admin-table">
                        <thead><tr><th>Order</th><th>Book</th><th>Role</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($user->purchases->concat($user->sales)->sortByDesc('created_at') as $o)
                                <tr>
                                    <td><a href="{{ route('orders.show', $o) }}" class="text-decoration-none">#{{ $o->id }}</a></td>
                                    <td>{{ $o->listing_title }}</td>
                                    <td class="small">{{ $o->buyer_id === $user->id ? 'Buyer' : 'Seller' }}</td>
                                    <td><span class="ub-badge {{ $orderStatus[$o->status] ?? 'ub-badge-cat' }}">{{ $o->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center ub-muted py-3">No orders.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
