@extends('layouts.app')

@section('title', 'Admin')

@section('content')
<div class="container py-4">
    @include('admin._nav', ['title' => 'Dashboard', 'subtitle' => 'What is happening on UniBook right now.'])

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.users.index') }}" class="ub-card ub-stat d-block text-decoration-none text-reset h-100">
                <div class="ub-muted small fw-semibold"><i class="bi bi-people me-1"></i>Users</div>
                <div class="num">{{ $users['total'] }}</div>
                <div class="small ub-muted">{{ $users['admins'] }} {{ Str::plural('admin', $users['admins']) }} · {{ $users['banned'] }} banned</div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.listings.index') }}" class="ub-card ub-stat d-block text-decoration-none text-reset h-100">
                <div class="ub-muted small fw-semibold"><i class="bi bi-collection me-1"></i>Listings</div>
                <div class="num">{{ $listings->sum() }}</div>
                <div class="small ub-muted">{{ $listings['available'] ?? 0 }} available · {{ $listings['reserved'] ?? 0 }} reserved · {{ $listings['sold'] ?? 0 }} sold</div>
                @if ($hiddenListings)<div class="small text-danger fw-semibold">{{ $hiddenListings }} hidden by admin</div>@endif
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.orders.index') }}" class="ub-card ub-stat d-block text-decoration-none text-reset h-100">
                <div class="ub-muted small fw-semibold"><i class="bi bi-receipt me-1"></i>Orders</div>
                <div class="num">{{ $orders->sum() }}</div>
                <div class="small ub-muted">{{ $orders['pending'] ?? 0 }} booked · {{ $orders['completed'] ?? 0 }} completed · {{ $orders['cancelled'] ?? 0 }} cancelled</div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('admin.books.index') }}" class="ub-card ub-stat d-block text-decoration-none text-reset h-100">
                <div class="ub-muted small fw-semibold"><i class="bi bi-journal-bookmark me-1"></i>Catalogue books</div>
                <div class="num">{{ $books }}</div>
                <div class="small ub-muted">Searched first when a seller scans</div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="ub-card p-3 p-md-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="ub-section-title">Newest users</h2>
                    <a href="{{ route('admin.users.index') }}" class="small fw-semibold">All users</a>
                </div>
                <div class="d-flex flex-column gap-2">
                    @forelse ($latestUsers as $u)
                        <a href="{{ route('admin.users.show', $u) }}" class="d-flex align-items-center gap-2 text-decoration-none text-reset">
                            <span class="ub-avatar">{{ collect(preg_split('/\s+/', trim($u->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</span>
                            <span class="flex-grow-1 min-w-0">
                                <span class="d-block fw-semibold text-truncate">{{ $u->name }}
                                    @if ($u->isAdmin())<span class="ub-badge ub-badge-admin ms-1">Admin</span>@endif
                                    @if ($u->isBanned())<span class="ub-badge ub-badge-banned ms-1">Banned</span>@endif
                                </span>
                                <span class="small ub-muted text-truncate d-block">{{ $u->email }}</span>
                            </span>
                            <span class="small ub-muted text-nowrap">{{ $u->created_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <div class="ub-muted small">No users yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="ub-card p-3 p-md-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="ub-section-title">Newest listings</h2>
                    <a href="{{ route('admin.listings.index') }}" class="small fw-semibold">All listings</a>
                </div>
                <div class="d-flex flex-column gap-2">
                    @forelse ($latestListings as $l)
                        <div class="d-flex align-items-center gap-2">
                            @if ($l->coverImageUrl())
                                <img class="ub-cover" style="width: 36px; flex: 0 0 36px;" src="{{ $l->coverImageUrl() }}" alt="" loading="lazy">
                            @else
                                <div class="ub-cover ub-cover-empty" style="width: 36px; flex: 0 0 36px;"><div><i class="bi bi-book" style="font-size: 1rem;"></i></div></div>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('books.show', $l) }}" class="d-block fw-semibold text-truncate text-decoration-none text-reset">{{ $l->title }}</a>
                                <span class="small ub-muted">{{ $l->user->name }} · RM{{ number_format((float) $l->price, 2) }}</span>
                            </div>
                            @if ($l->hidden_at)<span class="ub-badge ub-badge-hidden">Hidden</span>@endif
                            <span class="small ub-muted text-nowrap">{{ $l->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="ub-muted small">No listings yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
