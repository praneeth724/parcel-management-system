@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2 class="fw-bold mb-1">Sign in</h2>
    <p class="text-muted mb-4">Welcome back. Enter your credentials to continue.</p>

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label required">Email address</label>
            <input type="email"
                   id="email"
                   name="email"
                   value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   placeholder="you@company.lk"
                   autocomplete="username"
                   autofocus
                   required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="form-label required mb-0">Password</label>
                <a href="{{ route('password.request') }}" class="small text-decoration-none">Forgot password?</a>
            </div>

            <div class="input-group mt-1">
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password"
                       required>
                <button class="btn btn-outline-secondary" type="button" data-password-toggle="#password" tabindex="-1">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-check mb-4">
            <input type="checkbox" name="remember" id="remember" value="1" class="form-check-input" @checked(old('remember'))>
            <label for="remember" class="form-check-label">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
        </button>
    </form>

    @if (config('courier.registration.enabled'))
        <p class="text-center text-muted small mt-4 mb-0">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-decoration-none fw-semibold">Create one</a>
        </p>
    @endif

    {{-- Demo credentials, shown only in local development. --}}
    @if (app()->environment('local'))
        <div class="card mt-4 border-0 bg-light">
            <div class="card-body py-3">
                <div class="small fw-semibold mb-2">
                    <i class="bi bi-info-circle"></i> Demo accounts (password: <code>password</code>)
                </div>
                <ul class="list-unstyled small text-muted mb-0">
                    <li><strong>Super Admin</strong> — admin@swifttrack.lk</li>
                    <li><strong>Branch Manager</strong> — manager.colombo@swifttrack.lk</li>
                    <li><strong>Dispatcher</strong> — dispatcher.colombo@swifttrack.lk</li>
                    <li><strong>Driver</strong> — driver1@swifttrack.lk</li>
                </ul>
            </div>
        </div>
    @endif
@endsection
