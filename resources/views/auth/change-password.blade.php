@extends('layouts.app')

@section('title', 'Change password')

@section('content')
    <x-page-header title="Change password"
                   subtitle="Updating your password signs you out of every other device." />

    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('password.change.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <x-form.field name="current_password"
                                      type="password"
                                      label="Current password"
                                      autocomplete="current-password"
                                      :required="true" />

                        <hr class="my-4">

                        <div class="mb-3">
                            <label for="password" class="form-label required">New password</label>
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
                                      label="Confirm new password"
                                      autocomplete="new-password"
                                      :required="true" />

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-lock me-1"></i> Update password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 bg-light">
                <div class="card-body p-4">
                    <h6 class="fw-bold"><i class="bi bi-shield-check text-success"></i> What happens next</h6>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Every other browser session is signed out immediately.</li>
                        <li>All of your API access tokens are revoked.</li>
                        <li>This session stays signed in, so you can keep working.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
