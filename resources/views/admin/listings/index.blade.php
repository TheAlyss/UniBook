@extends('layouts.app')

@section('title', 'Listings · Admin')

@section('content')
@php $statusClass = ['available' => 'ub-match-high', 'reserved' => 'ub-match-medium', 'sold' => 'ub-badge-cat']; @endphp
<div class="container py-4">
    @include('admin._nav', ['title' => 'Listings', 'subtitle' => 'Hide or delete fake, spam or unsuitable listings.'])

    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control" style="max-width: 320px;" placeholder="Title, author, course or seller" aria-label="Search listings">
        <select name="status" class="form-select w-auto" aria-label="Status">
            <option value="">Any status</option>
            @foreach (\App\Models\Listing::STATUSES as $value => [$label])
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="visibility" class="form-select w-auto" aria-label="Visibility">
            <option value="">Visible and hidden</option>
            <option value="hidden" @selected($visibility === 'hidden')>Hidden only</option>
        </select>
        <button class="btn btn-primary">Search</button>
        @if ($q !== '' || $status !== '' || $visibility !== '')<a href="{{ route('admin.listings.index') }}" class="btn btn-link">Clear</a>@endif
    </form>

    <div class="ub-card overflow-hidden">
        <div class="table-responsive">
            <table class="table ub-admin-table">
                <thead><tr><th>Book</th><th>Seller</th><th class="text-end">Price</th><th>Status</th><th>Posted</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($listings as $l)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($l->coverImageUrl())
                                        <img class="ub-cover" src="{{ $l->coverImageUrl() }}" alt="" loading="lazy">
                                    @else
                                        <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book" style="font-size: 1rem;"></i></div></div>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('books.show', $l) }}" class="fw-semibold text-decoration-none">{{ $l->title }}</a>
                                        <div class="small ub-muted">{{ $l->category }}@if ($l->course_code) · {{ $l->course_code }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td class="small">
                                <a href="{{ route('admin.users.show', $l->user) }}" class="text-decoration-none">{{ $l->user->name }}</a>
                                @if ($l->user->isBanned())<span class="ub-badge ub-badge-banned ms-1">Banned</span>@endif
                            </td>
                            <td class="text-end text-nowrap">RM{{ number_format((float) $l->price, 2) }}</td>
                            <td>
                                <span class="ub-badge {{ $statusClass[$l->status] ?? 'ub-badge-cat' }}">{{ $l->statusLabel() }}</span>
                                @if ($l->hidden_at)<span class="ub-badge ub-badge-hidden">Hidden</span>@endif
                            </td>
                            <td class="small ub-muted text-nowrap">{{ $l->created_at->format('j M Y') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($l->hidden_at)
                                    <form method="POST" action="{{ route('admin.listings.unhide', $l) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-soft"><i class="bi bi-eye me-1"></i>Show</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.listings.hide', $l) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-soft"><i class="bi bi-eye-slash me-1"></i>Hide</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.listings.destroy', $l) }}" class="d-inline" onsubmit="return confirm('Delete this listing? This cannot be undone.');">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center ub-muted py-4">No listings match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $listings->links() }}</div>
</div>
@endsection
