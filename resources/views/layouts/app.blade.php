<!DOCTYPE html>
<html lang="en" data-theme="purple">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'UniBook') · UniBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @include('layouts._theme')
    @stack('head')
</head>
<body class="d-flex flex-column min-vh-100">

@php
    $user = auth()->user();
    $initials = $user ? collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : '';
@endphp

<nav class="ub-navbar sticky-top">
    <div class="container d-flex align-items-center py-2 gap-3">
        <a class="ub-logo" href="{{ route('home') }}"><span class="ub-logo-mark"><i class="bi bi-book-half"></i></span>UniBook</a>

        <div class="d-none d-md-flex gap-1 ms-3">
            <a class="ub-nav-link {{ request()->routeIs('home', 'books.*') ? 'active' : '' }}" href="{{ route('home') }}">Find Books</a>
            <a class="ub-nav-link {{ request()->routeIs('listings.create') ? 'active' : '' }}" href="{{ route('listings.create') }}">Sell a Book</a>
            @auth
                <a class="ub-nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">My Orders</a>
            @endauth
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
            @auth
                <div class="dropdown">
                    <button class="btn p-0 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                        <span class="ub-avatar">{{ $initials }}</span>
                        <span class="fw-semibold small d-none d-sm-inline">{{ $user->name }}</span>
                        <i class="bi bi-chevron-down small ub-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px;">
                        <li><span class="dropdown-item-text small ub-muted">{{ $user->email }}</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('listings.mine') }}"><i class="bi bi-collection me-2"></i>My Listings</a></li>
                        <li><a class="dropdown-item" href="{{ route('orders.index') }}"><i class="bi bi-receipt me-2"></i>My Orders</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log Out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <a class="ub-nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">Log In</a>
                <a class="btn btn-primary btn-sm px-3" href="{{ route('register') }}">Register</a>
            @endauth
        </div>
    </div>
</nav>

@if (session('success'))
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show mb-0" style="border-radius: 12px;">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif

@if (session('error'))
    <div class="container mt-3">
        <div class="alert alert-danger alert-dismissible fade show mb-0" style="border-radius: 12px;">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif

<main class="flex-grow-1">
    @yield('content')
</main>

<footer class="border-top py-3 mt-5" style="background: var(--ub-surface);">
    <div class="container small ub-muted d-flex justify-content-between flex-wrap gap-2">
        <span>UniBook · Second-hand book marketplace for UiTM Perlis, Arau Campus</span>
        <span>ICT651 Project</span>
    </div>
</footer>

{{-- Phone: bottom navigation --}}
<nav class="ub-bottom-nav d-md-none" aria-label="Main">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home', 'books.*') ? 'active' : '' }}"><i class="bi bi-search"></i>Find</a>
    <a href="{{ route('listings.create') }}" class="sell {{ request()->routeIs('listings.create') ? 'active' : '' }}"><i class="bi bi-plus-lg"></i>Sell</a>
    <a href="{{ auth()->check() ? route('orders.index') : route('login') }}" class="{{ request()->routeIs('orders.*') ? 'active' : '' }}"><i class="bi bi-receipt"></i>Orders</a>
    <a href="{{ auth()->check() ? route('listings.mine') : route('login') }}" class="{{ request()->routeIs('listings.mine', 'listings.edit', 'login', 'register') ? 'active' : '' }}"><i class="bi bi-person"></i>{{ auth()->check() ? 'Profile' : 'Log In' }}</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
