{{-- Shared fields for the create and edit listing forms. $listing is null when creating. --}}
@php $listing = $listing ?? null; @endphp

<h2 class="ub-section-title mb-3">Book details</h2>

{{-- Filled by the book lookup / photo scan. Only a URL or file path is stored for the cover. --}}
<input type="hidden" id="isbn" name="isbn" value="{{ old('isbn', $listing?->isbn) }}">
<input type="hidden" id="cover_url" name="cover_url" value="{{ old('cover_url', $listing?->cover_url) }}">
<input type="hidden" id="cover_source" name="cover_source" value="{{ old('cover_source', $listing?->cover_source) }}">
<input type="hidden" id="book_source" name="book_source" value="{{ old('book_source') }}">
<input type="hidden" id="book_id" name="book_id" value="{{ old('book_id', $listing?->book_id) }}">

@php
    $coverValue = old('cover_url', $listing?->cover_url);
    $coverSrc = \App\Support\Covers::url($coverValue);
    $isbnValue = old('isbn', $listing?->isbn);
    $coverLabels = ['google_books' => 'Google Books', 'open_library' => 'Open Library', 'seller_photo' => 'your photo'];
    $coverSourceValue = old('cover_source', $listing?->cover_source);
@endphp
<div id="cover-preview" class="ub-cover-preview mb-3">
    <img id="cover-preview-img" src="{{ $coverSrc }}" alt="Book cover" class="ub-cover {{ $coverSrc ? '' : 'd-none' }}">
    <div id="cover-preview-empty" class="ub-cover ub-cover-empty {{ $coverSrc ? 'd-none' : '' }}"><div><i class="bi bi-image"></i></div></div>
    <div class="small flex-grow-1 min-w-0">
        <div class="fw-semibold">Cover: <span id="cover-source-text">{{ $coverSrc ? ($coverLabels[$coverSourceValue] ?? 'book lookup') : 'none yet' }}</span></div>
        <div class="ub-muted">ISBN: <span id="isbn-text">{{ $isbnValue ?: '-' }}</span></div>
        <div class="ub-muted">Details from: <span id="source-text">{{ $listing ? 'your listing' : 'scan a photo or pick a match' }}</span></div>
    </div>
    <div class="d-flex flex-column align-items-end gap-1">
        {{-- Sell page only (needs its script): pick another cover when the one found is wrong. --}}
        <button type="button" id="cover-change" class="btn btn-sm btn-link p-0 d-none">Wrong cover?</button>
        <button type="button" id="clear-lookup" class="btn btn-sm btn-link text-danger p-0 {{ $coverSrc || $isbnValue ? '' : 'd-none' }}">Remove</button>
    </div>
</div>
<div id="cover-options" class="mb-3 d-none">
    <div class="small fw-semibold mb-2">Choose the right cover</div>
    <div id="cover-options-list" class="d-flex flex-wrap gap-2"></div>
    <div id="cover-options-note" class="small ub-muted mt-2"></div>
</div>
@error('cover_url') <div class="text-danger small mb-3">{{ $message }}</div> @enderror

<div class="mb-3">
    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
    <input type="text" id="title" name="title" value="{{ old('title', $listing?->title) }}"
           class="form-control @error('title') is-invalid @enderror" placeholder="e.g. RESTful Web APIs">
    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="author" class="form-label">Author <span class="ub-muted fw-normal">(optional)</span></label>
    <input type="text" id="author" name="author" value="{{ old('author', $listing?->author) }}"
           class="form-control @error('author') is-invalid @enderror" placeholder="e.g. Leonard Richardson">
    @error('author') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="edition" class="form-label">Edition <span class="ub-muted fw-normal">(optional)</span></label>
        <input type="text" id="edition" name="edition" value="{{ old('edition', $listing?->edition) }}"
               class="form-control @error('edition') is-invalid @enderror" placeholder="e.g. 2nd Edition">
        @error('edition') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="publisher" class="form-label">Publisher <span class="ub-muted fw-normal">(optional)</span></label>
        <input type="text" id="publisher" name="publisher" value="{{ old('publisher', $listing?->publisher) }}"
               class="form-control @error('publisher') is-invalid @enderror" placeholder="e.g. O'Reilly Media">
        @error('publisher') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<hr>
<h2 class="ub-section-title mb-3">Listing details</h2>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
            <option value="">Choose...</option>
            @foreach (\App\Models\Listing::CATEGORIES as $category)
                <option value="{{ $category }}" @selected(old('category', $listing?->category) === $category)>{{ $category }}</option>
            @endforeach
        </select>
        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="course" class="form-label">Course code <span class="ub-muted fw-normal">(optional)</span></label>
        <input type="text" id="course" name="course" value="{{ old('course', $listing?->course_code) }}" list="course-list"
               class="form-control ub-uppercase @error('course') is-invalid @enderror" placeholder="e.g. ICT651">
        <datalist id="course-list">
            @foreach ($courses as $code)
                <option value="{{ $code }}">
            @endforeach
        </datalist>
        <div class="form-text">Only if the book is used for a UiTM course.</div>
        @error('course') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
        <div class="input-group has-validation">
            <span class="input-group-text">RM</span>
            <input type="number" id="price" name="price" value="{{ old('price', $listing?->price) }}" step="0.01" min="1"
                   class="form-control @error('price') is-invalid @enderror" placeholder="35.00">
            @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="condition" class="form-label">Condition <span class="text-danger">*</span></label>
        <select id="condition" name="condition" class="form-select @error('condition') is-invalid @enderror">
            <option value="">Choose...</option>
            @foreach ($conditions as $condition)
                <option value="{{ $condition }}" @selected(old('condition', $listing?->condition) === $condition)>{{ $condition }}</option>
            @endforeach
        </select>
        @error('condition') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

@if (! empty($locked))
    <div class="alert alert-warning py-2 small">
        <i class="bi bi-lock"></i> A buyer has booked this book, so its status stays <strong>Reserved</strong>
        until the order is completed or cancelled. You can still edit the other details.
    </div>
@elseif (isset($statuses))
    <div class="mb-3" style="max-width: 260px;">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
            @foreach ($statuses as $value => [$label, $color])
                <option value="{{ $value }}" @selected(old('status', $listing?->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">Sold listings are hidden from search.</div>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
@endif

<div class="mb-3">
    <label for="description" class="form-label">Notes for buyers</label>
    <textarea id="description" name="description" rows="3"
              class="form-control @error('description') is-invalid @enderror"
              placeholder="e.g. Some highlighting in Chapter 3, cover in good shape.">{{ old('description', $listing?->description) }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<p class="small ub-muted mb-3"><span class="text-danger">*</span> Required field</p>
