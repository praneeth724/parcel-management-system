@extends('layouts.guest')

@section('title', 'Create account')

@section('content')
    <h2 class="fw-bold mb-1">Create your account</h2>
    <p class="text-muted mb-4">
        New accounts start with the Dispatcher role. An administrator can promote you later.
    </p>

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf

        <x-form.field name="name"
                      label="Full name"
                      placeholder="Nimal Perera"
                      autocomplete="name"
                      :required="true"
                      autofocus />

        <x-form.field name="email"
                      type="email"
                      label="Email address"
                      placeholder="you@company.lk"
                      autocomplete="username"
                      :required="true" />

        <x-form.field name="phone"
                      label="Mobile number"
                      placeholder="0771234567"
                      autocomplete="tel"
                      help="Sri Lankan mobile number, e.g. 0771234567" />

        <x-form.field name="branch_id"
                      label="Branch"
                      placeholder="— Select your branch —"
                      :options="$branches->pluck('label', 'id')"
                      help="Which location will you be working from?" />

        <div class="mb-3">
            <label for="password" class="form-label required">Password</label>
            <div class="input-group">
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="new-password"
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
                      label="Confirm password"
                      autocomplete="new-password"
                      :required="true" />

        <div class="form-check mb-4">
            <input type="checkbox"
                   name="terms"
                   id="terms"
                   value="1"
                   class="form-check-input @error('terms') is-invalid @enderror"
                   @checked(old('terms'))>
            <label for="terms" class="form-check-label small">
                I agree to the acceptable use policy for this system.
            </label>
            @error('terms')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-person-plus me-1"></i> Create account
        </button>
    </form>

    <p class="text-center text-muted small mt-4 mb-0">
        Already registered?
        <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">Sign in</a>
    </p>
@endsection
