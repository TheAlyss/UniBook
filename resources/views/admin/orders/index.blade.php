@extends('layouts.app')

@section('title', 'Orders · Admin')

@section('content')
@php $statusClass = ['pending' => 'ub-match-medium', 'completed' => 'ub-match-high', 'cancelled' => 'ub-badge-banned']; @endphp
<div class="container py-4">
    @include('admin._nav', ['title' => 'Orders', 'subtitle' => 'Cancel an order that is stuck, so the book becomes available again.'])

    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control" style="max-width: 320px;" placeholder="Book, buyer or seller" aria-label="Search orders">
        <select name="status" class="form-select w-auto" aria-label="Status">
            <option value="">Any status</option>
            @foreach (\App\Models\Order::STATUSES as $value => [$label])
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary">Search</button>
        @if ($q !== '' || $status !== '')<a href="{{ route('admin.orders.index') }}" class="btn btn-link">Clear</a>@endif
    </form>

    <div class="ub-card overflow-hidden">
        <div class="table-responsive">
            <table class="table ub-admin-table">
                <thead><tr><th>Order</th><th>Book</th><th>Buyer</th><th>Seller</th><th class="text-end">Price</th><th>Meetup</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($orders as $o)
                        <tr>
                            <td><a href="{{ route('orders.show', $o) }}" class="fw-semibold text-decoration-none">#{{ $o->id }}</a>
                                <div class="small ub-muted text-nowrap">{{ $o->created_at->format('j M Y') }}</div></td>
                            <td>{{ $o->listing_title }}</td>
                            <td class="small"><a href="{{ route('admin.users.show', $o->buyer) }}" class="text-decoration-none">{{ $o->buyer->name }}</a></td>
                            <td class="small"><a href="{{ route('admin.users.show', $o->seller) }}" class="text-decoration-none">{{ $o->seller->name }}</a></td>
                            <td class="text-end text-nowrap">RM{{ number_format((float) $o->amount, 2) }}</td>
                            <td class="small">@if ($o->meetup){{ $o->meetup->name }}<div class="ub-muted text-nowrap">{{ $o->meetup->meetup_time->format('j M, g:i A') }}</div>@else<span class="ub-muted">–</span>@endif</td>
                            <td><span class="ub-badge {{ $statusClass[$o->status] ?? 'ub-badge-cat' }}">{{ $o->statusLabel() }}</span></td>
                            <td class="text-end text-nowrap">
                                @if ($o->status === 'pending')
                                    <form method="POST" action="{{ route('admin.orders.cancel', $o) }}" class="d-inline" onsubmit="return confirm('Cancel order #{{ $o->id }}? The book becomes available again.');">@csrf
                                        <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center ub-muted py-4">No orders match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
