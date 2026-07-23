@extends('layouts.app')

@section('title', 'My profile')

@section('content')
    <x-page-header title="My profile"
                   subtitle="Your role and branch can only be changed by an administrator." />

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">Personal details</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <img src="{{ $user->avatar_url }}" alt="" id="avatarPreview" class="avatar avatar--lg">
                            <div class="flex-grow-1">
                                <label for="avatar" class="form-label">Profile photo</label>
                                <input type="file"
                                       name="avatar"
                                       id="avatar"
                                       accept="image/*"
                                       data-preview="#avatarPreview"
                                       class="form-control @error('avatar') is-invalid @enderror">
                                @error('avatar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-text">
                                        JPG, PNG or WebP, up to {{ round(config('courier.uploads.max_image_kb') / 1024) }} MB.
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <x-form.field name="name"
                                      label="Full name"
                                      :value="$user->name"
                                      :required="true" />

                        <x-form.field name="email"
                                      type="email"
                                      label="Email address"
                                      :value="$user->email"
                                      :required="true"
                                      help="Changing this requires you to verify the new address." />

                        <x-form.field name="phone"
                                      label="Mobile number"
                                      :value="$user->phone"
                                      placeholder="0771234567"
                                      help="Sri Lankan mobile number" />

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">Account</h6>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Role</dt>
                        <dd class="col-7"><x-status-badge :status="$user->role" /></dd>

                        <dt class="col-5 text-muted fw-normal">Branch</dt>
                        <dd class="col-7">{{ $user->branch?->name ?? 'All branches' }}</dd>

                        @if ($user->driver)
                            <dt class="col-5 text-muted fw-normal">Driver record</dt>
                            <dd class="col-7">
                                <a href="{{ route('drivers.show', $user->driver) }}" class="text-decoration-none">
                                    {{ $user->driver->driver_code }}
                                </a>
                            </dd>
                        @endif

                        <dt class="col-5 text-muted fw-normal">Email verified</dt>
                        <dd class="col-7">
                            @if ($user->hasVerifiedEmail())
                                <span class="text-success"><i class="bi bi-patch-check"></i> Verified</span>
                            @else
                                <a href="{{ route('verification.notice') }}" class="text-warning text-decoration-none">
                                    <i class="bi bi-exclamation-circle"></i> Verify now
                                </a>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Last sign-in</dt>
                        <dd class="col-7">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'This is your first' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Member since</dt>
                        <dd class="col-7">{{ $user->created_at->format('d M Y') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">Security</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Changing your password signs you out of every other device and revokes
                        your API tokens.
                    </p>
                    <a href="{{ route('password.change') }}" class="btn btn-outline-primary">
                        <i class="bi bi-key me-1"></i> Change password
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
