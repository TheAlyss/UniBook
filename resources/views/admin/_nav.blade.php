{{-- Admin area header + section tabs. $title: page heading, $subtitle: optional line under it. --}}
@push('head')
    <style>
        .ub-admin-tabs { display: flex; gap: .35rem; flex-wrap: wrap; background: var(--ub-surface); border: 1px solid var(--ub-border);
                         border-radius: 999px; padding: 4px; width: fit-content; max-width: 100%; }
        .ub-admin-tabs a { border-radius: 999px; color: var(--ub-muted); font-weight: 600; padding: .4rem .9rem; text-decoration: none; font-size: .9rem; }
        .ub-admin-tabs a.active { background: var(--ub-primary); color: #fff; }
        .ub-admin-table { margin: 0; }
        .ub-admin-table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: var(--ub-muted); font-weight: 700; background: var(--ub-bg); }
        .ub-admin-table td { vertical-align: middle; }
        .ub-admin-table .ub-cover { width: 40px; flex: 0 0 40px; }
        .ub-stat { padding: 1rem 1.1rem; }
        .form-select.w-auto { padding-right: 2.6rem; }
        .ub-stat .num { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
    </style>
@endpush

<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
    <div>
        <div class="small fw-semibold text-primary mb-1"><i class="bi bi-shield-lock me-1"></i>Admin</div>
        <h1 class="h3 fw-bold mb-1">{{ $title }}</h1>
        @isset($subtitle)<div class="ub-muted">{{ $subtitle }}</div>@endisset
    </div>
    <nav class="ub-admin-tabs" aria-label="Admin sections">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><i class="bi bi-people me-1"></i>Users</a>
        <a href="{{ route('admin.listings.index') }}" class="{{ request()->routeIs('admin.listings.*') ? 'active' : '' }}"><i class="bi bi-collection me-1"></i>Listings</a>
        <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"><i class="bi bi-receipt me-1"></i>Orders</a>
        <a href="{{ route('admin.books.index') }}" class="{{ request()->routeIs('admin.books.*') ? 'active' : '' }}"><i class="bi bi-journal-bookmark me-1"></i>Catalogue</a>
    </nav>
</div>
