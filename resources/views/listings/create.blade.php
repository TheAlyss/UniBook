@extends('layouts.app')

@section('title', 'Sell a Book')

@push('head')
    <style>
        #other-results .ub-result { opacity: .85; }
        .ub-dropzone input[type=file] { display: none; }
        .ub-result:focus-visible { outline: 3px solid rgba(var(--ub-primary-rgb), .35); outline-offset: 2px; }
        .ub-cover-option { width: 92px; border: 2px solid var(--ub-border); border-radius: var(--ub-radius-sm); background: var(--ub-surface);
                           padding: .4rem; text-align: center; font-size: .72rem; font-weight: 600; color: var(--ub-muted); }
        .ub-cover-option .ub-cover { width: 100%; margin-bottom: .3rem; }
        .ub-cover-option:hover { border-color: rgba(var(--ub-primary-rgb), .45); }
        .ub-cover-option.selected { border-color: var(--ub-primary); color: var(--ub-primary); box-shadow: 0 0 0 3px rgba(var(--ub-primary-rgb), .12); }
    </style>
@endpush

@section('content')
<div class="container py-4">
    <h1 class="h3 fw-bold mb-1">Sell a book</h1>
    <p class="ub-muted mb-3">Scan the cover and we'll fill in most of the details for you. Any book: textbooks, references, novels, religious books and more.</p>

    @if ($errors->any())
        <div class="alert alert-danger" style="border-radius: 12px;">
            <i class="bi bi-exclamation-triangle"></i> Please fix the errors below and try again.
        </div>
    @endif

    {{-- Where the seller is: 1 find the book -> 2 check the details -> 3 price & post --}}
    <div class="ub-card p-3 mb-4">
        <div class="ub-steps" id="steps">
            <div class="ub-step active" data-step="1"><span class="n">1</span><span class="lbl">Find book</span></div>
            <div class="ub-step-line"></div>
            <div class="ub-step" data-step="2"><span class="n">2</span><span class="lbl">Check details</span></div>
            <div class="ub-step-line"></div>
            <div class="ub-step" data-step="3"><span class="n">3</span><span class="lbl">Price &amp; post</span></div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        {{-- LEFT: find the book (UniBook catalogue -> Google Books -> Open Library) --}}
        <div class="col-lg-7">
            <div class="ub-card p-3 p-md-4">
                <h2 class="ub-section-title mb-3"><i class="bi bi-magic text-primary me-2"></i>Find your book</h2>

                <label class="ub-dropzone d-block mb-0" id="dropzone" for="scan-input">
                    <div id="drop-idle">
                        <div class="ub-dropzone-icon"><i class="bi bi-camera"></i></div>
                        <div class="fw-bold mb-1">Scan a photo of the cover or ISBN</div>
                        <div class="ub-muted small">Drag a photo here or tap to choose. Front cover (title) or back cover (ISBN near the barcode). Clear, straight and well-lit works best.</div>
                    </div>
                    <div id="drop-preview" class="d-none align-items-center gap-3 text-start">
                        <img id="scan-preview" class="ub-scan-preview" alt="Your photo">
                        <div class="min-w-0">
                            <div class="fw-bold">Photo added</div>
                            <div class="ub-muted small text-truncate" id="scan-name">cover.jpg</div>
                            <div class="small text-primary fw-semibold mt-1">Tap to choose another photo</div>
                        </div>
                    </div>
                    <input type="file" id="scan-input" accept="image/*" capture="environment">
                </label>
                <div class="d-flex gap-2 mt-3">
                    <label class="btn btn-primary mb-0" id="scan-btn" for="scan-input">
                        <span class="spinner-border spinner-border-sm d-none" id="scan-spinner"></span>
                        <i class="bi bi-camera me-1" id="scan-icon"></i>Scan Photo
                    </label>
                    {{-- Shown after a scan or search: start again (the temporary photo is deleted). --}}
                    <button type="button" id="reset-btn" class="btn btn-outline-secondary d-none" title="Clear the photo, the results and the form">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </button>
                </div>

                <div class="d-flex align-items-center gap-3 my-3"><hr class="flex-grow-1 m-0"><span class="ub-muted small fw-semibold">OR</span><hr class="flex-grow-1 m-0"></div>

                <form id="lookup-form" class="input-group" novalidate>
                    <span class="input-group-text bg-white" style="border-radius: 12px 0 0 12px;"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="lookup-q" class="form-control" placeholder="Type an ISBN or title, e.g. 9781449358068" maxlength="100" aria-label="ISBN or title">
                    <button type="submit" id="lookup-btn" class="btn btn-outline-primary" style="border-radius: 0 12px 12px 0;">
                        <span class="spinner-border spinner-border-sm d-none" id="lookup-spinner"></span> Search
                    </button>
                </form>

                {{-- Progress while waiting for the server (real steps, not a timer) --}}
                <div class="ub-card p-3 mt-4 d-none" id="loading" style="box-shadow: none;">
                    <ul class="ub-progress-list" id="progress-list"></ul>
                </div>

                <div id="lookup-message" class="small mt-3"></div>
                <details id="ocr-details" class="small mt-1 d-none">
                    <summary class="ub-muted">Text read from your photo</summary>
                    <pre id="ocr-text" class="p-2 rounded mt-1 mb-0" style="white-space: pre-wrap; background: var(--ub-bg);"></pre>
                </details>

                {{-- Real matches --}}
                <div id="results-box" class="mt-3 d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-bold">Is this your book?</div>
                        <span class="ub-muted small" id="results-count"></span>
                    </div>
                    <div class="d-grid gap-2" id="lookup-results"></div>
                </div>

                {{-- Results that did not match the search words are hidden by default (Part 3). --}}
                <div id="other-results-toggle" class="mt-1 d-none">
                    <button type="button" id="other-results-btn" class="btn btn-link px-0">Show other results</button>
                </div>
                <div id="other-results-box" class="d-none">
                    <p class="small ub-muted mb-2">These results look like a different book, but you can still pick one.</p>
                    <div class="d-grid gap-2" id="other-results"></div>
                </div>

                {{-- After a photo scan: what was read from the photo. Nothing is filled until the seller clicks the button. --}}
                <div id="none-of-these" class="ub-ai-panel mt-3 d-none">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <div class="fw-bold">Read from your photo</div>
                        <span id="photo-read-by" class="ub-badge ub-badge-ai"></span>
                    </div>
                    {{-- Why the AI did not label the text (e.g. free daily limit used up). --}}
                    <div id="photo-ai-note" class="small ub-muted fst-italic mb-2 d-none"></div>
                    <dl id="photo-fields"></dl>
                    <button type="button" id="none-of-these-btn" class="btn btn-soft w-100">
                        <i class="bi bi-image me-1"></i><span id="none-of-these-label">Fill the form from my photo</span>
                    </button>
                    <div class="small ub-muted mt-2 text-center">Uses the details above and your photo as the cover. Please check them.</div>
                </div>

                {{-- Always offered: the book may not be online (e.g. UiTM modules). --}}
                <div id="manual-entry" class="text-center mt-3">
                    <span class="ub-muted small">Not found?</span>
                    <button type="button" id="manual-entry-btn" class="btn btn-link btn-sm p-0 align-baseline">Enter the details manually</button>
                </div>
            </div>
        </div>

        {{-- RIGHT: check the details, choose a category and add the price. Never submitted automatically. --}}
        <div class="col-lg-5">
            <form method="POST" action="{{ route('listings.store') }}" class="ub-card p-3 p-md-4 ub-sticky" id="listing-form" novalidate>
                @csrf
                @include('listings._form')

                <div class="mb-3">
                    <div class="ub-muted small fw-semibold mb-2">Preview</div>
                    <div class="ub-mini-card" id="mini-preview"></div>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary flex-fill">Cancel</a>
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-upload me-1"></i>Post Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const lookupUrl = @json(route('listings.lookup'));
    const scanUrl = @json(route('listings.scan'));
    const detailsUrl = @json(route('listings.book-details'));
    const scanPhotoUrl = @json(url('listings/scan-photo')); // + "/<name>" (DELETE = discard a temporary scan)
    const csrfToken = @json(csrf_token());

    const FIELDS = ['title', 'author', 'edition', 'publisher'];
    const SOURCE_LABELS = { catalog: 'UniBook catalogue', google_books: 'Google Books', open_library: 'Open Library', manual: 'your photo (please check)' };
    const COVER_LABELS = { google_books: 'Google Books', open_library: 'Open Library', seller_photo: 'your photo' };

    const $ = id => document.getElementById(id);
    const form = $('lookup-form'), input = $('lookup-q'), message = $('lookup-message');
    const results = $('lookup-results'), resultsBox = $('results-box'), others = $('other-results');
    const othersToggle = $('other-results-toggle'), othersBox = $('other-results-box'), othersBtn = $('other-results-btn');
    const ocrDetails = $('ocr-details'), noneOfThese = $('none-of-these');

    let scannedPhoto = null; // {path, url} of the last photo, used as the cover when no better one exists
    let scannedGuess = null; // title / author / edition / publisher read from the last photo (for "None of these")

    // ---------- helpers ----------
    function setMessage(text, type) {
        message.className = 'small mt-3 text-' + type;
        message.textContent = text;
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    }

    function isIsbn(q) {
        return /^(97[89]\d{10}|\d{9}[\dX])$/i.test(String(q || '').replace(/[\s-]/g, ''));
    }

    // ---------- steps: 1 find book -> 2 check details -> 3 price & post ----------
    let found = false; // a book was picked, auto-filled or the seller chose to type it in
    function updateSteps() {
        const ready = ['title', 'category', 'price', 'condition'].every(id => $(id).value.trim() !== '');
        const step = ready ? 3 : (found || $('title').value.trim() ? 2 : 1);
        document.querySelectorAll('#steps .ub-step').forEach(function (s) {
            const k = Number(s.dataset.step);
            s.classList.toggle('active', k === step);
            s.classList.toggle('done', k < step);
            s.querySelector('.n').innerHTML = k < step ? '<i class="bi bi-check-lg"></i>' : k;
        });
    }

    // ---------- live preview of the listing card ----------
    function updatePreview() {
        const box = $('mini-preview');
        box.innerHTML = '';
        const img = $('cover-preview-img');
        if (img.getAttribute('src') && !img.classList.contains('d-none')) {
            const cover = el('img', 'ub-cover'); cover.src = img.src; cover.alt = 'Cover';
            box.appendChild(cover);
        } else {
            const empty = el('div', 'ub-cover ub-cover-empty'); empty.appendChild(el('div')).appendChild(el('i', 'bi bi-book'));
            box.appendChild(empty);
        }
        const info = el('div', 'd-flex flex-column gap-1 min-w-0');
        const price = parseFloat($('price').value);
        const priceEl = el('div', 'ub-price'); priceEl.style.fontSize = '1.05rem';
        priceEl.appendChild(el('small', '', 'RM'));
        priceEl.appendChild(document.createTextNode(isNaN(price) ? '0.00' : price.toFixed(2)));
        info.appendChild(priceEl);
        const title = el('div', 'ub-book-title', $('title').value.trim() || 'Book title'); title.style.fontSize = '.9rem';
        info.appendChild(title);
        info.appendChild(el('div', 'ub-book-author', $('author').value.trim() || ' '));
        const badges = el('div', 'd-flex flex-wrap gap-1');
        const course = $('course').value.trim().toUpperCase();
        if (course) badges.appendChild(el('span', 'ub-badge ub-badge-course', course));
        if ($('category').value) badges.appendChild(el('span', 'ub-badge ub-badge-cat', $('category').value));
        if ($('condition').value) badges.appendChild(el('span', 'ub-badge ub-badge-cat', $('condition').value));
        info.appendChild(badges);
        box.appendChild(info);
    }

    function refresh() { updateSteps(); updatePreview(); }

    ['title', 'author', 'edition', 'publisher', 'category', 'course', 'price', 'condition'].forEach(function (id) {
        $(id).addEventListener('input', refresh);
        $(id).addEventListener('change', refresh);
    });

    // Remember which fields the seller typed in themselves: auto-fill never overwrites those.
    FIELDS.forEach(function (id) {
        $(id).addEventListener('input', function () {
            this.dataset.typed = '1';
            unmark(this);
        });
    });

    function mark(field) {
        field.classList.add('ub-autofilled');
        if (!field.parentElement.querySelector('.ub-autofill-note')) {
            const note = el('div', 'ub-autofill-note show');
            note.innerHTML = '<i class="bi bi-stars me-1"></i>Auto-filled, please check.';
            field.insertAdjacentElement('afterend', note);
        }
    }

    function unmark(field) {
        field.classList.remove('ub-autofilled');
        const note = field.parentElement.querySelector('.ub-autofill-note');
        if (note) note.remove();
    }

    /**
     * Fill fields. Without overwrite, a field the seller typed in is left alone.
     * With overwrite (the seller picked a book), fields the book has no value for are emptied,
     * so nothing from an earlier book or photo is left behind.
     */
    function fill(values, overwrite) {
        const skipped = [];
        FIELDS.forEach(function (id) {
            const value = values[id];
            const field = $(id);
            if (!value) {
                if (overwrite) { field.value = ''; field.dataset.typed = ''; unmark(field); }
                return;
            }
            if (!overwrite && field.dataset.typed === '1' && field.value.trim() !== '') {
                skipped.push(id);
                return;
            }
            field.value = value;
            field.dataset.typed = '';
            mark(field);
        });
        refresh();
        return skipped;
    }

    function setCover(path, url, source) {
        $('cover_url').value = path || '';
        $('cover_source').value = path ? (source || '') : '';
        const img = $('cover-preview-img');
        if (url) { img.src = url; img.classList.remove('d-none'); }
        else { img.removeAttribute('src'); img.classList.add('d-none'); }
        $('cover-preview-empty').classList.toggle('d-none', !!url);
        $('cover-source-text').textContent = path ? (COVER_LABELS[source] || 'book lookup') : 'none yet';
        updatePreview();
        updateCoverButton();
    }

    /** Part 5 cover order: book cover (Google Books / Open Library) -> seller's photo -> placeholder. */
    function chooseCover(book) {
        const path = book && (book.cover_path || book.cover_url);
        bookCover = path ? { path: path, url: book.cover_url, source: book.cover_source } : null;
        hideCoverOptions();
        if (path) return setCover(path, book.cover_url, book.cover_source);
        if (scannedPhoto) return setCover(scannedPhoto.path, scannedPhoto.url, 'seller_photo');
        setCover(null, null, null);
    }

    // ---------- "Wrong cover?": the cover found online can be wrong (Google Books sometimes serves the
    // picture of another book), so the seller can pick another one. Nothing is chosen automatically here.
    let bookCover = null; // cover of the book the seller picked (Google Books / Open Library / catalogue)

    function openLibraryCoverUrl() {
        const isbn = String($('isbn').value || '').replace(/[^0-9X]/gi, '');
        return /^(97[89]\d{10}|\d{9}[\dX])$/i.test(isbn) ? 'https://covers.openlibrary.org/b/isbn/' + isbn + '-M.jpg?default=false' : null;
    }

    function updateCoverButton() {
        const hasChoice = !!(bookCover || scannedPhoto || openLibraryCoverUrl());
        $('cover-change').classList.toggle('d-none', !hasChoice);
    }

    function hideCoverOptions() {
        $('cover-options').classList.add('d-none');
        $('cover-options-list').innerHTML = '';
    }

    function coverOption(label, path, url, source) {
        const btn = el('button', 'ub-cover-option');
        btn.type = 'button';
        if (url) {
            const img = el('img', 'ub-cover'); img.src = url; img.alt = label; img.loading = 'lazy';
            btn.appendChild(img);
        } else {
            const empty = el('div', 'ub-cover ub-cover-empty'); empty.appendChild(el('div')).appendChild(el('i', 'bi bi-slash-circle'));
            btn.appendChild(empty);
        }
        btn.appendChild(document.createTextNode(label));
        btn.classList.toggle('selected', ($('cover_url').value || null) === (path || null));
        btn.addEventListener('click', function () {
            setCover(path, url, source);
            document.querySelectorAll('.ub-cover-option').forEach(b => b.classList.toggle('selected', b === btn));
        });
        return btn;
    }

    function showCoverOptions() {
        const list = $('cover-options-list');
        list.innerHTML = '';
        const labels = { google_books: 'Google Books', open_library: 'Open Library', seller_photo: 'Your photo' };
        if (bookCover && bookCover.source !== 'seller_photo') {
            list.appendChild(coverOption(labels[bookCover.source] || 'Book cover', bookCover.path, bookCover.url, bookCover.source));
        }
        // Open Library cover by ISBN: only offered when the picture really exists (404 = none).
        const olUrl = openLibraryCoverUrl();
        if (olUrl && !(bookCover && bookCover.path === olUrl)) {
            const probe = new Image();
            probe.onload = function () {
                if ($('cover-options').classList.contains('d-none')) return;
                const none = list.querySelector('[data-none]');
                list.insertBefore(coverOption('Open Library', olUrl, olUrl, 'open_library'), none);
            };
            probe.src = olUrl;
        }
        if (scannedPhoto) list.appendChild(coverOption('Your photo', scannedPhoto.path, scannedPhoto.url, 'seller_photo'));
        const none = coverOption('No cover', null, null, null);
        none.dataset.none = '1';
        list.appendChild(none);
        $('cover-options-note').textContent = scannedPhoto
            ? 'Pick the picture that matches your book.'
            : 'Pick the picture that matches your book. You can also scan a photo of your book to use it as the cover.'
                + (olUrl ? '' : ' Type the ISBN in the search box to look for an Open Library cover.');
        $('cover-options').classList.remove('d-none');
    }

    $('cover-change').addEventListener('click', function () {
        if ($('cover-options').classList.contains('d-none')) showCoverOptions(); else hideCoverOptions();
    });

    function showPreview(isbn, sourceKey) {
        $('isbn-text').textContent = isbn || '-';
        $('source-text').textContent = SOURCE_LABELS[sourceKey] || sourceKey || 'book lookup';
        $('clear-lookup').classList.remove('d-none');
    }

    function clearResults() {
        results.innerHTML = '';
        others.innerHTML = '';
        resultsBox.classList.add('d-none');
        othersToggle.classList.add('d-none');
        othersBox.classList.add('d-none');
        noneOfThese.classList.add('d-none');
    }

    /** Marks the chosen result card (results stay visible, so the seller can change their mind). */
    function select(card) {
        document.querySelectorAll('.ub-result').forEach(c => {
            c.classList.toggle('selected', c === card);
            c.setAttribute('aria-pressed', c === card ? 'true' : 'false');
        });
    }

    /** Use a book from the lookup. overwrite = true when the seller clicked it themselves. */
    function applyBook(book, overwrite) {
        const skipped = fill(book, overwrite);
        $('isbn').value = book.isbn || '';
        $('book_source').value = book.source_key || '';
        $('book_id').value = book.book_id || '';
        chooseCover(book);
        showPreview(book.isbn, book.source_key);
        found = true;
        refresh();
        loadDetails(book);
        return skipped;
    }

    /**
     * Search results often have no edition or publisher. Ask the server for the full record of the
     * chosen book (Google Books volume / Open Library edition) and fill the fields that are still empty.
     * For a scanned book, the edition printed on the cover is used when the record has none.
     */
    async function loadDetails(book) {
        const missing = ['author', 'edition', 'publisher'].filter(id => !$(id).value.trim());
        if (!missing.length) return;
        let details = {};
        if (book.google_id || book.isbn) {
            const note = el('div', 'small ub-muted mt-1');
            note.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            note.appendChild(document.createTextNode('Looking up the ' + missing.join(', ') + '...'));
            note.id = 'details-loading';
            message.insertAdjacentElement('afterend', note);
            const params = new URLSearchParams({ google_id: book.google_id || '', isbn: book.isbn || '', title: book.title || '' });
            try {
                const res = await fetch(detailsUrl + '?' + params, { headers: { 'Accept': 'application/json' } });
                if (res.ok) details = await res.json();
            } catch (e) { /* the seller can still type them */ }
            note.remove();
        }
        if (!details.edition && scannedGuess && scannedGuess.edition) details.edition = scannedGuess.edition;
        const values = {};
        missing.forEach(id => { if (details[id] && !$(id).value.trim()) values[id] = details[id]; });
        fill(values, false);
    }

    /** No match, so fill what was read from the photo. Saved as source = manual. */
    function applyAutofill(guess, overwrite) {
        const skipped = fill(guess, !!overwrite);
        $('isbn').value = guess.isbn || '';
        $('book_source').value = 'manual';
        $('book_id').value = '';
        chooseCover(null);
        showPreview(guess.isbn, 'manual');
        found = true;
        refresh();
        return skipped;
    }

    function skippedNote(skipped) {
        return skipped.length ? ' We kept what you typed in: ' + skipped.join(', ') + '.' : '';
    }

    function pickBook(book, card) {
        select(card);
        applyBook(book, true);
        setMessage('Details filled in from ' + book.source + '. Please check them, then choose a category and add the price.', 'success');
    }

    const SOURCE_BADGE = { catalog: ['catalogue', 'bi-database'], google_books: ['', 'bi-google'], open_library: ['', 'bi-book'] };

    /** One card per book. The whole card is a button that fills the form. */
    function renderCards(container, books) {
        container.innerHTML = '';
        books.forEach(function (book) {
            const card = el('button', 'ub-result');
            card.type = 'button';
            card.setAttribute('aria-pressed', 'false');
            card.setAttribute('aria-label', 'Use ' + book.title);
            if (book.cover_url) {
                const img = el('img', 'ub-cover');
                img.src = book.cover_url; img.alt = 'Cover'; img.loading = 'lazy';
                card.appendChild(img);
            } else {
                const ph = el('div', 'ub-cover ub-cover-empty');
                ph.appendChild(el('div')).appendChild(el('i', 'bi bi-book'));
                card.appendChild(ph);
            }
            const info = el('div', 'pe-4 min-w-0');
            const badges = el('div', 'd-flex flex-wrap gap-1 mb-1');
            const badge = SOURCE_BADGE[book.source_key] || ['', 'bi-search'];
            const source = el('span', 'ub-badge ub-badge-source ' + badge[0]);
            source.appendChild(el('i', 'bi ' + badge[1]));
            source.appendChild(document.createTextNode(book.source || 'Book lookup'));
            badges.appendChild(source);
            info.appendChild(badges);
            info.appendChild(el('div', 'fw-bold lh-sm', book.title));
            info.appendChild(el('div', 'small ub-muted', book.author || 'Author not listed'));
            const meta = [book.publisher, book.published_year].filter(Boolean).join(' · ');
            if (meta) info.appendChild(el('div', 'small ub-muted', meta));
            card.appendChild(info);
            const check = el('span', 'ub-result-check'); check.appendChild(el('i', 'bi bi-check-lg'));
            card.appendChild(check);
            card.addEventListener('click', () => pickBook(book, card));
            container.appendChild(card);
        });
    }

    function showResults(list) {
        if (!list || !list.length) return;
        renderCards(results, list);
        $('results-count').textContent = list.length + (list.length === 1 ? ' match' : ' matches');
        resultsBox.classList.remove('d-none');
    }

    function showOthers(list) {
        if (!list || !list.length) return;
        renderCards(others, list);
        othersBtn.textContent = 'Show other results (' + list.length + ')';
        othersToggle.classList.remove('d-none');
    }

    othersBtn.addEventListener('click', function () {
        const hidden = othersBox.classList.toggle('d-none');
        othersBtn.textContent = (hidden ? 'Show' : 'Hide') + ' other results (' + others.children.length + ')';
    });

    /** Shows what was read from the photo, with the button to fill the form. */
    function showPhotoFields(guess, readBy, hasMatches, aiNote) {
        const list = $('photo-fields');
        list.innerHTML = '';
        if (!guess) return;
        [['title', 'Title'], ['author', 'Author'], ['edition', 'Edition'], ['publisher', 'Publisher']].forEach(function (pair) {
            list.appendChild(el('dt', '', pair[1]));
            list.appendChild(el('dd', guess[pair[0]] ? '' : 'empty', guess[pair[0]] || 'Not found on cover'));
        });
        const badge = $('photo-read-by');
        badge.className = 'ub-badge ' + (readBy === 'ai' ? 'ub-badge-ai' : 'ub-badge-source');
        badge.innerHTML = readBy === 'ai' ? '<i class="bi bi-stars"></i>labelled by AI' : '<i class="bi bi-text-paragraph"></i>read by text rules';
        $('photo-ai-note').textContent = aiNote || '';
        $('photo-ai-note').classList.toggle('d-none', !aiNote);
        $('none-of-these-label').textContent = hasMatches ? 'None of these: fill the form from my photo' : 'Fill the form from my photo';
        noneOfThese.classList.remove('d-none');
    }

    // "Fill the form from my photo" / "None of these": use the details read from the photo + the photo as cover.
    $('none-of-these-btn').addEventListener('click', function () {
        // Remove details left by a book picked earlier (fields the seller typed are kept).
        FIELDS.forEach(function (id) {
            const field = $(id);
            if (field.dataset.typed !== '1') { field.value = ''; unmark(field); }
        });
        select(null);
        const skipped = applyAutofill(scannedGuess || {});
        if (scannedPhoto) setCover(scannedPhoto.path, scannedPhoto.url, 'seller_photo');
        setMessage('Filled in from the text on your photo, with your photo as the cover. Please check every field.' + skippedNote(skipped), 'success');
        $('title').focus();
    });

    $('manual-entry-btn').addEventListener('click', function () {
        select(null);
        $('book_source').value = 'manual';
        $('book_id').value = '';
        if (!$('cover_url').value) chooseCover(null); // keep the seller's photo as the cover if there is one
        found = true;
        refresh();
        setMessage('Fill in the book details in the form.', 'muted');
        const title = $('title');
        title.scrollIntoView({ behavior: 'smooth', block: 'center' });
        title.focus({ preventScroll: true });
    });

    $('clear-lookup').addEventListener('click', function () {
        ['isbn', 'book_source', 'book_id'].forEach(id => $(id).value = '');
        FIELDS.forEach(id => unmark($(id)));
        bookCover = null;
        hideCoverOptions();
        setCover(null, null, null);
        $('isbn-text').textContent = '-';
        $('source-text').textContent = 'scan a photo or pick a match';
        this.classList.add('d-none');
        select(null);
    });

    // ---------- progress while waiting (real steps: each one ends when that work is done) ----------
    function progress(labels) {
        const list = $('progress-list');
        list.innerHTML = '';
        labels.forEach(function (label) {
            const li = el('li');
            const ic = el('span', 'ic'); ic.appendChild(el('i', 'bi bi-circle'));
            li.appendChild(ic); li.appendChild(document.createTextNode(label));
            list.appendChild(li);
        });
        $('loading').classList.remove('d-none');
    }

    function progressAt(index) {
        document.querySelectorAll('#progress-list li').forEach(function (li, i) {
            li.className = i < index ? 'done' : (i === index ? 'doing' : '');
            li.querySelector('.ic').innerHTML = i < index
                ? '<i class="bi bi-check-circle-fill"></i>'
                : (i === index ? '<span class="spinner-border spinner-border-sm"></span>' : '<i class="bi bi-circle"></i>');
        });
    }

    function progressEnd() { $('loading').classList.add('d-none'); }

    // ---------- responses (text search and photo scan) ----------
    async function handleResponse(res, query) {
        const data = await res.json().catch(() => ({}));
        progressEnd();

        const isScan = !!data.photo;
        if (isScan) {
            scannedPhoto = data.photo;
            scannedGuess = data.autofill || null;
        }
        if (data.ocr_text) {
            $('ocr-text').textContent = data.ocr_text;
            ocrDetails.classList.remove('d-none');
        }
        if (data.query && !input.value) input.value = data.query;

        if (res.ok) {
            const matches = data.results || [];
            // ISBN search with exactly one result: fill straight away.
            if (matches.length === 1 && (isIsbn(data.query || data.isbn) || isIsbn(query))) {
                showResults(matches);
                const skipped = applyBook(matches[0], false);
                select(results.firstElementChild);
                setMessage('Book found by ISBN (' + matches[0].source + '). Details filled in, please check the highlighted fields.' + skippedNote(skipped), 'success');
                return;
            }
            // Several possible matches: the seller chooses.
            setMessage(data.message, 'muted');
            showResults(matches);
            showOthers(data.other_results);
            if (isScan) showPhotoFields(data.autofill, data.autofill_source, true, data.ai_note);
            return;
        }

        if (res.status === 404 || res.status === 422 || res.status === 503) {
            const text = data.message || 'No matching book found.';
            if (data.autofill) {
                // Nothing is filled automatically: the seller checks the details and clicks the button.
                showPhotoFields(data.autofill, data.autofill_source, false, data.ai_note);
            } else if (scannedPhoto && !$('cover_url').value) {
                chooseCover(null); // at least keep the seller's photo as the cover
            }
            setMessage(text, data.autofill ? 'muted' : 'warning');
            showOthers(data.other_results);
            return;
        }

        if (res.status === 429) {
            setMessage('Too many requests. Please wait a minute and try again.', 'danger');
        } else {
            const firstError = data.errors && Object.values(data.errors)[0];
            setMessage((firstError && firstError[0]) || data.message || 'Something went wrong. Please fill in the details manually.', 'danger');
        }
    }

    function startLoading() {
        clearResults();
        ocrDetails.classList.add('d-none');
        setMessage('', 'muted');
        $('reset-btn').classList.remove('d-none');
    }

    /** Deletes the seller's temporary scan on the server, unless it is the cover the form still uses. */
    function discardScan(photo) {
        if (!photo || !photo.path || !photo.path.startsWith('scan/') || $('cover_url').value === photo.path) return;
        const name = photo.path.substring('scan/'.length);
        fetch(scanPhotoUrl + '/' + encodeURIComponent(name), {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        }).catch(() => { /* old scans are pruned after 24 hours anyway */ });
    }

    function showDropPreview(src, name) {
        const idle = $('drop-idle'), preview = $('drop-preview');
        if (src) {
            $('scan-preview').src = src;
            $('scan-name').textContent = name || 'cover.jpg';
            idle.classList.add('d-none');
            preview.classList.remove('d-none'); preview.classList.add('d-flex');
        } else {
            $('scan-preview').removeAttribute('src');
            idle.classList.remove('d-none');
            preview.classList.add('d-none'); preview.classList.remove('d-flex');
        }
    }

    // Reset: the seller cancels. Clears the photo, the results and the whole form.
    $('reset-btn').addEventListener('click', function () {
        const photo = scannedPhoto;
        scannedPhoto = null;
        scannedGuess = null;
        found = false;

        clearResults();
        progressEnd();
        ocrDetails.classList.add('d-none');
        $('ocr-text').textContent = '';
        input.value = '';
        setMessage('', 'muted');
        showDropPreview(null);

        FIELDS.forEach(function (id) { const f = $(id); f.value = ''; f.dataset.typed = ''; unmark(f); });
        ['isbn', 'book_source', 'book_id', 'course', 'price', 'description'].forEach(id => $(id).value = '');
        ['category', 'condition'].forEach(id => $(id).selectedIndex = 0);
        bookCover = null;
        hideCoverOptions();
        setCover(null, null, null);
        $('isbn-text').textContent = '-';
        $('source-text').textContent = 'scan a photo or pick a match';
        $('clear-lookup').classList.add('d-none');

        discardScan(photo); // cover was cleared above, so the temporary photo is deleted
        this.classList.add('d-none');
        refresh();
        input.focus();
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const q = input.value.trim();
        if (q.length < 2) { setMessage('Please enter an ISBN or at least 2 characters of the title.', 'danger'); return; }

        startLoading();
        progress(['Searching UniBook, Google Books and Open Library...']);
        progressAt(0);
        $('lookup-spinner').classList.remove('d-none'); $('lookup-btn').disabled = true;
        try {
            await handleResponse(await fetch(lookupUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }), q);
        } catch (err) {
            progressEnd();
            setMessage('Could not reach the server. Please fill in the details manually.', 'danger');
        } finally {
            $('lookup-spinner').classList.add('d-none'); $('lookup-btn').disabled = false;
        }
    });

    // ---------- photo scan (OCR.space) ----------
    const scanInput = $('scan-input');
    const scanBtn = $('scan-btn');
    const dropzone = $('dropzone');

    // Resize in the browser so the upload stays under OCR.space's 1 MB free limit.
    function resizeImage(file, maxSize = 1600, quality = 0.85) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            img.onload = function () {
                const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(img.src);
                canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('resize failed')), 'image/jpeg', quality);
            };
            img.onerror = () => reject(new Error('not an image'));
            img.src = URL.createObjectURL(file);
        });
    }

    async function scanFile(file) {
        if (!file) return;
        if (!/^image\//.test(file.type)) { setMessage('The file must be a photo (JPG, PNG or WEBP).', 'danger'); return; }

        // A new photo replaces the last one: delete the old temporary scan (unless it is the cover in use).
        discardScan(scannedPhoto);
        scannedPhoto = null;

        showDropPreview(URL.createObjectURL(file), file.name);
        startLoading();
        progress(['Preparing your photo', 'Reading the text and finding the book', 'Showing the results']);
        progressAt(0);
        $('scan-spinner').classList.remove('d-none');
        $('scan-icon').classList.add('d-none');
        scanBtn.classList.add('disabled');

        try {
            const blob = await resizeImage(file);
            progressAt(1);
            const body = new FormData();
            body.append('image', blob, 'cover.jpg');
            input.value = '';
            const res = await fetch(scanUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: body,
            });
            progressAt(2);
            await handleResponse(res, '');
        } catch (err) {
            progressEnd();
            setMessage('Could not process this photo. Please try another photo or type the details manually.', 'danger');
        } finally {
            $('scan-spinner').classList.add('d-none');
            $('scan-icon').classList.remove('d-none');
            scanBtn.classList.remove('disabled');
            scanInput.value = '';
        }
    }

    scanInput.addEventListener('change', () => scanFile(scanInput.files[0]));

    // Drag a photo onto the scan area.
    ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.add('drag'); }));
    ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.remove('drag'); }));
    dropzone.addEventListener('drop', e => scanFile(e.dataTransfer.files[0]));

    // After a failed Post (validation errors), the form keeps the seller's values.
    found = $('title').value.trim() !== '';
    refresh();
})();
</script>
@endsection
