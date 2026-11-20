@extends('layouts.app')

@section('title', 'Edit Listing')

@section('content')
<div class="container py-4" style="max-width: 760px;">
    <a href="{{ route('listings.mine') }}" class="small fw-semibold text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>My Listings
    </a>

    <h1 class="h3 fw-bold mt-2 mb-4">Edit listing</h1>

    @if ($errors->any())
        <div class="alert alert-danger" style="border-radius: 12px;">
            <i class="bi bi-exclamation-triangle"></i> Please fix the errors below and try again.
        </div>
    @endif

    <form method="POST" action="{{ route('listings.update', $listing) }}" class="ub-card p-3 p-md-4" novalidate>
        @csrf
        @method('PUT')
        @include('listings._form')
        <div class="d-flex gap-2">
            <a href="{{ route('listings.mine') }}" class="btn btn-outline-secondary flex-fill">Cancel</a>
            <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-save me-1"></i>Save Changes</button>
        </div>
    </form>
</div>
@endsection
