@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h2 class="fw-bold mb-1">Forgot your password?</h2>
    <p class="text-muted mb-4">
        Enter the email address on your account and we'll send you a link to set a new password.
    </p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <x-form.field name="email"
                      type="email"
                      label="Email address"
                      placeholder="you@company.lk"
                      autocomplete="username"
                      :required="true"
                      autofocus />

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-envelope me-1"></i> Email password reset link
        </button>
    </form>

    <p class="text-center text-muted small mt-4 mb-0">
        <a href="{{ route('login') }}" class="text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to sign in
        </a>
    </p>
@endsection
