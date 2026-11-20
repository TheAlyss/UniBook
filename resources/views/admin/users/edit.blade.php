@extends('layouts.app')

@section('title', 'Edit ' . $user->name . ' · Admin')

@section('content')
<div class="container py-4">
    @include('admin._nav', ['title' => 'Edit user', 'subtitle' => $user->email])

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="ub-card p-3 p-md-4" style="max-width: 640px;" novalidate>
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror">
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-check form-switch mb-4">
            <input type="hidden" name="is_admin" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_admin" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))>
            <label class="form-check-label" for="is_admin">Admin (can open this admin area)</label>
        </div>

        <h2 class="ub-section-title mb-1">Reset password</h2>
        <p class="small ub-muted mb-3">Leave empty to keep the current password. A new password also signs the user out of the API.</p>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="password" class="form-label">New password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">At least 8 characters.</div>
            </div>
            <div class="col-md-6">
                <label for="password_confirmation" class="form-label">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary flex-fill">Cancel</a>
            <button class="btn btn-primary flex-fill"><i class="bi bi-save me-1"></i>Save changes</button>
        </div>
    </form>
</div>
@endsection
