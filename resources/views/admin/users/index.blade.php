@extends('layouts.app')

@section('title', 'Users · Admin')

@section('content')
<div class="container py-4">
    @include('admin._nav', ['title' => 'Users', 'subtitle' => $users->total() . ' ' . Str::plural('account', $users->total())])

    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control" style="max-width: 320px;" placeholder="Search name or email" aria-label="Search users">
        <select name="filter" class="form-select w-auto" aria-label="Filter">
            <option value="">All users</option>
            <option value="admins" @selected($filter === 'admins')>Admins</option>
            <option value="banned" @selected($filter === 'banned')>Banned</option>
        </select>
        <button class="btn btn-primary">Search</button>
        @if ($q !== '' || $filter !== '')<a href="{{ route('admin.users.index') }}" class="btn btn-link">Clear</a>@endif
    </form>

    <div class="ub-card overflow-hidden">
        <div class="table-responsive">
            <table class="table ub-admin-table">
                <thead><tr><th>User</th><th>Role</th><th class="text-end">Listings</th><th class="text-end">Orders</th><th>Joined</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $u) }}" class="fw-semibold text-decoration-none">{{ $u->name }}</a>
                                <div class="small ub-muted">{{ $u->email }}</div>
                            </td>
                            <td>
                                @if ($u->isAdmin())<span class="ub-badge ub-badge-admin">Admin</span>@else<span class="ub-badge ub-badge-cat">User</span>@endif
                                @if ($u->isBanned())<span class="ub-badge ub-badge-banned">Banned</span>@endif
                            </td>
                            <td class="text-end">{{ $u->listings_count }}</td>
                            <td class="text-end">{{ $u->purchases_count + $u->sales_count }}</td>
                            <td class="small ub-muted text-nowrap">{{ $u->created_at->format('j M Y') }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-soft">View</a>
                                <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center ub-muted py-4">No users match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $users->links() }}</div>
</div>
@endsection
