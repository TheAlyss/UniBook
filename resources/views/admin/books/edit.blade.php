@extends('layouts.app')

@section('title', 'Edit book · Admin')

@section('content')
@php
    $coverSources = ['' => 'None', 'google_books' => 'Google Books', 'open_library' => 'Open Library', 'seller_photo' => 'Seller photo'];
    $olCover = $book->isbn ? 'https://covers.openlibrary.org/b/isbn/' . $book->isbn . '-M.jpg?default=false' : null;
@endphp
<div class="container py-4">
    @include('admin._nav', ['title' => 'Edit catalogue book', 'subtitle' => $book->title])

    <form method="POST" action="{{ route('admin.books.update', $book) }}" class="ub-card p-3 p-md-4" style="max-width: 760px;" novalidate>
        @csrf
        @method('PUT')

        <div class="ub-cover-preview mb-4">
            @if ($book->coverImageUrl())
                <img class="ub-cover" src="{{ $book->coverImageUrl() }}" alt="Current cover">
            @else
                <div class="ub-cover ub-cover-empty"><div><i class="bi bi-image"></i></div></div>
            @endif
            <div class="small">
                <div class="fw-semibold">Current cover: {{ $coverSources[$book->cover_source ?? ''] ?? 'None' }}</div>
                <div class="ub-muted">Wrong picture? Paste another https image link below.</div>
                @if ($olCover)
                    <div class="ub-muted">Open Library cover for this ISBN: <code style="font-size: .75rem;">{{ $olCover }}</code></div>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" id="title" name="title" value="{{ old('title', $book->title) }}" class="form-control @error('title') is-invalid @enderror">
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="author" class="form-label">Author</label>
                <input type="text" id="author" name="author" value="{{ old('author', $book->author) }}" class="form-control @error('author') is-invalid @enderror">
            </div>
            <div class="col-md-6">
                <label for="publisher" class="form-label">Publisher</label>
                <input type="text" id="publisher" name="publisher" value="{{ old('publisher', $book->publisher) }}" class="form-control @error('publisher') is-invalid @enderror">
            </div>
            <div class="col-md-4">
                <label for="edition" class="form-label">Edition</label>
                <input type="text" id="edition" name="edition" value="{{ old('edition', $book->edition) }}" class="form-control @error('edition') is-invalid @enderror">
            </div>
            <div class="col-md-4">
                <label for="published_year" class="form-label">Year</label>
                <input type="text" id="published_year" name="published_year" value="{{ old('published_year', $book->published_year) }}" maxlength="4" class="form-control @error('published_year') is-invalid @enderror">
                @error('published_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="isbn" class="form-label">ISBN</label>
                <input type="text" id="isbn" name="isbn" value="{{ old('isbn', $book->isbn) }}" class="form-control @error('isbn') is-invalid @enderror">
                @error('isbn') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <label for="cover_url" class="form-label">Cover image link</label>
                <input type="text" id="cover_url" name="cover_url" value="{{ old('cover_url', $book->cover_url) }}" class="form-control @error('cover_url') is-invalid @enderror" placeholder="https://...">
                @error('cover_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="cover_source" class="form-label">Cover from</label>
                <select id="cover_source" name="cover_source" class="form-select @error('cover_source') is-invalid @enderror">
                    @foreach ($coverSources as $value => $label)
                        <option value="{{ $value }}" @selected(old('cover_source', $book->cover_source) === $value || ($value === '' && ! old('cover_source', $book->cover_source)))>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.books.index') }}" class="btn btn-outline-secondary flex-fill">Cancel</a>
            <button class="btn btn-primary flex-fill"><i class="bi bi-save me-1"></i>Save changes</button>
        </div>
    </form>
</div>
@endsection
