@extends('layouts.app')

@section('title', 'Book: ' . $listing->title)

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #map { height: 380px; }
        .ub-meetup .ub-check { color: var(--ub-primary); display: none; }
        .ub-meetup.selected .ub-check { display: inline; }
        .ub-step-head { display: flex; align-items: center; gap: .6rem; margin-bottom: .35rem; }
        .ub-step-head .n { width: 28px; height: 28px; border-radius: 50%; background: var(--ub-primary); color: #fff;
                           display: grid; place-items: center; font-size: .82rem; font-weight: 700; flex: 0 0 28px; }
        .ub-summary .ub-cover { width: 56px; flex: 0 0 56px; }
    </style>
@endpush

@section('content')
@php
    $pointIcons = [
        'library' => 'bi-book', 'mosque' => 'bi-moon-stars', 'anjung_siswa' => 'bi-people',
        'dewan_seri_semarak' => 'bi-building', 'kafeteria_apple' => 'bi-cup-hot', 'stadium' => 'bi-trophy',
    ];
    $cover = $listing->coverImageUrl();
@endphp

<div class="container py-4">
    <a href="{{ route('books.show', $listing) }}" class="small fw-semibold text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Back to the book
    </a>

    <h1 class="h3 fw-bold mt-2 mb-1">Book this book</h1>
    <p class="ub-muted mb-4">Choose where and when to meet <strong>{{ $listing->user->name }}</strong> on campus. You pay in cash when you meet.</p>

    <div class="row g-4">
        {{-- LEFT: the book, where you are, meetup points --}}
        <div class="col-lg-5">
            <div class="ub-card p-3 mb-3 d-flex gap-3 align-items-center ub-summary">
                @if ($cover)
                    <img class="ub-cover" src="{{ $cover }}" alt="{{ $listing->title }} cover">
                @else
                    <div class="ub-cover ub-cover-empty"><div><i class="bi bi-book"></i></div></div>
                @endif
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-truncate">{{ $listing->title }}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        @if ($listing->course_code)<span class="ub-badge ub-badge-course">{{ $listing->course_code }}</span>@endif
                        <span class="ub-badge ub-badge-cat">{{ $listing->category }}</span>
                        <span class="ub-badge ub-badge-cat">{{ $listing->condition }}</span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="ub-price"><small>RM</small>{{ number_format((float) $listing->price, 2) }}</div>
                    <div class="small ub-muted"><i class="bi bi-cash-coin"></i> Cash on meetup</div>
                </div>
            </div>

            <div class="ub-card p-3 p-md-4 mb-3">
                <div class="ub-step-head"><span class="n">1</span><h2 class="ub-section-title">Where are you now?</h2></div>
                <p class="small ub-muted mb-3">We'll sort the meetup points by distance and show the route.</p>
                <button type="button" id="use-location" class="btn btn-soft w-100 mb-2">
                    <i class="bi bi-crosshair me-1"></i>Use my current location
                </button>
                <form id="place-form" class="input-group" novalidate>
                    <input type="text" id="place" class="form-control" placeholder="Or type a place, e.g. Arau, Kangar" maxlength="100" aria-label="Your place" style="border-radius: 12px 0 0 12px;">
                    <button class="btn btn-outline-primary" type="submit" style="border-radius: 0 12px 12px 0;">Find</button>
                </form>
                <div id="locate-message" class="small mt-2 ub-muted"></div>
            </div>

            <div class="ub-card p-3 p-md-4">
                <div class="ub-step-head"><span class="n">2</span><h2 class="ub-section-title">Choose a meetup point</h2></div>
                <p class="small ub-muted mb-3">All points are public places inside UiTM Arau.</p>
                <div id="points" class="d-grid gap-2">
                    @foreach ($points as $point)
                        <button type="button" class="ub-meetup point-option {{ old('meetup_point') === $point['key'] ? 'selected' : '' }}" data-key="{{ $point['key'] }}">
                            <span class="ub-meetup-ic"><i class="bi {{ $pointIcons[$point['key']] ?? 'bi-geo-alt' }}"></i></span>
                            <span class="flex-grow-1 min-w-0">
                                <span class="d-block fw-semibold">{{ $point['name'] }}</span>
                                <span class="small ub-muted point-distance">Share your location to see the distance</span>
                            </span>
                            <i class="bi bi-check-circle-fill ub-check"></i>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- RIGHT: map, time, reserve --}}
        <div class="col-lg-7">
            <div class="ub-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="ub-section-title"><i class="bi bi-geo-alt text-primary me-2"></i>Meetup on campus</h2>
                    <span class="ub-badge ub-badge-source"><i class="bi bi-map"></i>OpenStreetMap</span>
                </div>
                <div class="ub-map"><div id="map"></div></div>
                <div id="route-info" class="small ub-muted pt-2"></div>
            </div>

            <form method="POST" action="{{ route('orders.store', $listing) }}" class="ub-card p-3 p-md-4" novalidate>
                @csrf
                <input type="hidden" name="meetup_point" id="meetup_point" value="{{ old('meetup_point') }}">
                <input type="hidden" name="from_lat" id="from_lat" value="{{ old('from_lat') }}">
                <input type="hidden" name="from_lng" id="from_lng" value="{{ old('from_lng') }}">
                <input type="hidden" name="from_place" id="from_place" value="{{ old('from_place') }}">

                <div class="ub-step-head mb-3"><span class="n">3</span><h2 class="ub-section-title">When do you want to meet?</h2></div>

                @error('meetup_point') <div class="alert alert-danger py-2 small" style="border-radius: 12px;">{{ $message }}</div> @enderror

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="meetup_time" class="form-label">Date and time <span class="text-danger">*</span></label>
                        <input type="datetime-local" id="meetup_time" name="meetup_time" value="{{ old('meetup_time') }}"
                               min="{{ now()->addHour()->format('Y-m-d\TH:i') }}" max="{{ now()->addDays(14)->format('Y-m-d\TH:i') }}"
                               class="form-control @error('meetup_time') is-invalid @enderror">
                        @error('meetup_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="note" class="form-label">Note for the seller <span class="ub-muted fw-normal">(optional)</span></label>
                        <input type="text" id="note" name="note" value="{{ old('note') }}" maxlength="255"
                               class="form-control @error('note') is-invalid @enderror" placeholder="e.g. I'll wear a blue shirt">
                        @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="ub-pay-note mb-3"><i class="bi bi-cash-coin"></i>Bring RM{{ number_format((float) $listing->price, 2) }} in cash. The seller marks the order completed after you meet.</div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="small ub-muted" id="chosen-point">No meetup point chosen yet.</span>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-bag-check me-1"></i>Reserve Book</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const points = @json($points);
    const campus = @json($campus);
    const suggestUrl = @json(route('meetups.suggest'));
    const routeUrl = @json(route('meetups.route'));
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--ub-primary').trim() || '#4b2c83';

    const map = L.map('map').setView([campus.lat, campus.lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const markers = {};
    points.forEach(function (p) {
        markers[p.key] = L.marker([p.lat, p.lng]).addTo(map).bindPopup(p.name).on('click', () => choosePoint(p.key));
    });

    let originMarker = null, routeLine = null, origin = null;
    const message = document.getElementById('locate-message');

    function fmtDistance(m) { return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : m + ' m'; }
    function fmtDuration(s) { return Math.max(1, Math.round(s / 60)) + ' min'; }

    function drawRoute(route, point) {
        if (routeLine) { map.removeLayer(routeLine); routeLine = null; }
        const info = document.getElementById('route-info');
        if (route && route.geometry && route.geometry.length) {
            routeLine = L.polyline(route.geometry, { color: primary, weight: 5 }).addTo(map);
            map.fitBounds(routeLine.getBounds(), { padding: [30, 30] });
            info.innerHTML = '<i class="bi bi-signpost-2 me-1"></i>';
            info.appendChild(document.createTextNode('Route to ' + point.name + ': ' + fmtDistance(route.distance_m) + ', about ' + fmtDuration(route.duration_s) + ' by road.'));
        } else if (origin) {
            map.fitBounds(L.latLngBounds([[origin.lat, origin.lng], [point.lat, point.lng]]), { padding: [30, 30] });
            info.textContent = 'Route preview is not available right now.';
        }
    }

    function choosePoint(key, route) {
        const point = points.find(p => p.key === key);
        if (!point) return;
        document.getElementById('meetup_point').value = key;
        document.querySelectorAll('.point-option').forEach(el => {
            el.classList.toggle('selected', el.dataset.key === key);
            el.setAttribute('aria-pressed', el.dataset.key === key ? 'true' : 'false');
        });
        const chosen = document.getElementById('chosen-point');
        chosen.innerHTML = '<i class="bi bi-geo-alt-fill text-primary me-1"></i>';
        chosen.appendChild(document.createTextNode(point.name));
        markers[key].openPopup();

        if (route !== undefined) { drawRoute(route, point); return; }
        if (!origin) { map.setView([point.lat, point.lng], 17); return; }

        fetch(routeUrl + '?' + new URLSearchParams({ from_lat: origin.lat, from_lng: origin.lng, point: key }), { headers: { Accept: 'application/json' } })
            .then(r => r.json()).then(d => drawRoute(d.route, point)).catch(() => drawRoute(null, point));
    }

    document.querySelectorAll('.point-option').forEach(el => el.addEventListener('click', () => choosePoint(el.dataset.key)));

    async function suggest(params) {
        message.className = 'small mt-2 ub-muted';
        message.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Finding the nearest meetup point...';
        try {
            const res = await fetch(suggestUrl + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const firstError = data.errors && Object.values(data.errors)[0];
                message.className = 'small mt-2 text-danger';
                message.textContent = (firstError && firstError[0]) || data.message || 'Could not find meetup points.';
                return;
            }

            origin = data.origin;
            document.getElementById('from_lat').value = origin.lat;
            document.getElementById('from_lng').value = origin.lng;
            document.getElementById('from_place').value = origin.name || '';
            if (originMarker) map.removeLayer(originMarker);
            originMarker = L.circleMarker([origin.lat, origin.lng], { radius: 8, color: '#d63384', fillOpacity: .8 })
                .addTo(map).bindPopup('You are here');

            data.points.forEach(function (p) {
                const el = document.querySelector('.point-option[data-key="' + p.key + '"] .point-distance');
                if (el) {
                    el.innerHTML = '<i class="bi bi-signpost-2"></i> ';
                    el.appendChild(document.createTextNode(fmtDistance(p.distance_m) + (p.duration_s ? ' · ' + fmtDuration(p.duration_s) : '') + (p.method === 'straight_line' ? ' (approx.)' : '')));
                }
            });
            // Re-order the list: nearest first.
            const list = document.getElementById('points');
            data.points.forEach(p => list.appendChild(document.querySelector('.point-option[data-key="' + p.key + '"]')));

            message.className = 'small mt-2 text-success';
            message.textContent = data.message;
            choosePoint(data.points[0].key, data.route);
        } catch (e) {
            message.className = 'small mt-2 text-danger';
            message.textContent = 'Could not reach the server. You can still pick a meetup point from the list.';
        }
    }

    document.getElementById('use-location').addEventListener('click', function () {
        if (!navigator.geolocation) { message.textContent = 'Your browser cannot share its location. Please type a place instead.'; return; }
        message.textContent = 'Getting your location...';
        navigator.geolocation.getCurrentPosition(
            pos => suggest({ lat: pos.coords.latitude.toFixed(6), lng: pos.coords.longitude.toFixed(6) }),
            () => { message.className = 'small mt-2 text-danger'; message.textContent = 'Location permission was denied. Please type a place instead.'; },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    document.getElementById('place-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const place = document.getElementById('place').value.trim();
        if (place.length < 2) { message.className = 'small mt-2 text-danger'; message.textContent = 'Please type at least 2 characters.'; return; }
        suggest({ place: place });
    });

    // Keep the previous choice after a validation error.
    const previous = document.getElementById('meetup_point').value;
    if (previous) choosePoint(previous);
})();
</script>
@endpush
