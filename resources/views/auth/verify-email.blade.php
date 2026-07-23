@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-envelope-check display-4 text-primary d-block mb-3"></i>

                    <h2 class="h4 fw-bold">Verify your email address</h2>

                    <p class="text-muted">
                        We sent a verification link to
                        <strong>{{ auth()->user()->email }}</strong>.
                        Click it to confirm the address belongs to you.
                    </p>

                    @if (config('mail.default') === 'log')
                        <div class="alert alert-info text-start small">
                            <i class="bi bi-info-circle"></i>
                            <strong>Development note:</strong> mail is set to the <code>log</code> driver,
                            so the verification link is written to
                            <code>storage/logs/laravel.log</code> instead of being emailed.
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-repeat me-1"></i> Resend verification email
                            </button>
                        </form>

                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                            Continue to dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
