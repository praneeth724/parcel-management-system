<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Sign in') &middot; {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell">
        {{-- Marketing panel, hidden below the lg breakpoint so the form gets
             the whole screen on a phone. --}}
        <aside class="auth-showcase">
            <a href="{{ route('track.index') }}" class="d-inline-flex align-items-center gap-2 text-white text-decoration-none mb-5">
                <i class="bi bi-box-seam-fill fs-3"></i>
                <span class="fs-4 fw-bold">{{ config('app.name') }}</span>
            </a>

            <h1 class="display-6 fw-bold mb-3" style="max-width: 22ch;">
                Every parcel, every driver, every branch — in one place.
            </h1>

            <p class="lead opacity-75 mb-5" style="max-width: 45ch;">
                Book shipments, dispatch drivers, and follow each delivery from pickup
                to doorstep with a full, auditable tracking history.
            </p>

            <div class="row g-4">
                @foreach ([
                    ['bi-truck', 'Live dispatch', 'Assign drivers and watch status change in real time.'],
                    ['bi-geo-alt', 'Full tracking', 'An immutable timeline for every single parcel.'],
                    ['bi-graph-up-arrow', 'Business insight', 'Revenue, success rates and driver performance.'],
                    ['bi-shield-check', 'Role-based access', 'Admins, managers, dispatchers and drivers.'],
                ] as [$icon, $heading, $copy])
                    <div class="col-sm-6">
                        <i class="bi {{ $icon }} fs-4 mb-2 d-block"></i>
                        <div class="fw-semibold">{{ $heading }}</div>
                        <div class="small opacity-75">{{ $copy }}</div>
                    </div>
                @endforeach
            </div>
        </aside>

        <div class="auth-form-panel">
            <div>
                <a href="{{ route('track.index') }}"
                   class="d-lg-none d-inline-flex align-items-center gap-2 text-decoration-none mb-4">
                    <i class="bi bi-box-seam-fill fs-3 text-primary"></i>
                    <span class="fs-5 fw-bold text-dark">{{ config('app.name') }}</span>
                </a>

                @include('layouts.partials.alerts')

                @yield('content')

                <p class="text-center text-muted small mt-4 mb-0">
                    <a href="{{ route('track.index') }}" class="text-decoration-none">
                        <i class="bi bi-search"></i> Track a parcel without signing in
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
