<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Track your parcel') &middot; {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="{{ route('track.index') }}">
                <i class="bi bi-box-seam-fill text-primary fs-4"></i>
                {{ config('app.name') }}
            </a>

            <div class="ms-auto">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Staff sign in
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="py-5">
        <div class="container">
            @include('layouts.partials.alerts')

            @yield('content')
        </div>
    </main>

    <footer class="border-top bg-white py-4 mt-auto">
        <div class="container d-flex flex-wrap justify-content-between gap-2 small text-muted">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}. Courier &amp; Parcel Management System.</span>
            <span>Need help? Call your nearest branch.</span>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
