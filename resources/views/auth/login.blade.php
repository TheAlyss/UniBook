@extends('layouts.app')

@section('title', 'Log In')

@section('content')
<div class="container py-5" style="max-width: 440px;">
    <div class="text-center mb-4">
        <span class="ub-logo-mark mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.6rem; border-radius: 16px;"><i class="bi bi-book-half"></i></span>
        <h1 class="h3 fw-bold mb-1">Welcome back</h1>
        <p class="ub-muted mb-0">Log in to sell or book second-hand books on campus.</p>
    </div>

    <div class="ub-card p-4">
        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autofocus autocomplete="email"
                       class="form-control @error('email') is-invalid @enderror" placeholder="you@student.uitm.edu.my">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Log In</button>
        </form>
    </div>

    <p class="text-center small mt-3 mb-0">
        Don't have an account? <a href="{{ route('register') }}" class="fw-semibold">Register</a>
    </p>
</div>
@endsection
