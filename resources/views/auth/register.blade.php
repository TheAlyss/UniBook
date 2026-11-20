@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="container py-5" style="max-width: 480px;">
    <div class="text-center mb-4">
        <span class="ub-logo-mark mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.6rem; border-radius: 16px;"><i class="bi bi-book-half"></i></span>
        <h1 class="h3 fw-bold mb-1">Create an account</h1>
        <p class="ub-muted mb-0">Join UniBook to buy and sell second-hand books on campus.</p>
    </div>

    <div class="ub-card p-4">
        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="name" class="form-label">Full name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" autofocus autocomplete="name"
                       class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email"
                       class="form-control @error('email') is-invalid @enderror" placeholder="you@student.uitm.edu.my">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror">
                <div class="form-text">At least 8 characters.</div>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
    </div>

    <p class="text-center small mt-3 mb-0">
        Already have an account? <a href="{{ route('login') }}" class="fw-semibold">Log in</a>
    </p>
</div>
@endsection
