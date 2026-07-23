<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    <div class="sidebar-backdrop"></div>

    @include('layouts.partials.sidebar')

    <div class="app-main">
        @include('layouts.partials.topbar')

        <main class="app-content">
            @include('layouts.partials.alerts')

            @yield('content')
        </main>

        <footer class="app-footer d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}. Courier &amp; Parcel Management System.</span>
            <span>Laravel {{ app()->version() }} &middot; PHP {{ PHP_VERSION }}</span>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
