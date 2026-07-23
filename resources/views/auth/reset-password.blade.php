@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <h2 class="fw-bold mb-1">Set a new password</h2>
    <p class="text-muted mb-4">Choose a password you haven't used on this account before.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.field name="email"
                      type="email"
                      label="Email address"
                      :value="$email"
                      autocomplete="username"
                      :required="true"
                      readonly />

        <div class="mb-3">
            <label for="password" class="form-label required">New password</label>
            <div class="input-group">
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="new-password"
                       autofocus
                       required>
                <button class="btn btn-outline-secondary" type="button" data-password-toggle="#password" tabindex="-1">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">
                At least 8 characters, with upper and lower case letters and a number.
            </div>
        </div>

        <x-form.field name="password_confirmation"
                      type="password"
                      label="Confirm new password"
                      autocomplete="new-password"
                      :required="true" />

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-shield-check me-1"></i> Reset password
        </button>
    </form>
@endsection
