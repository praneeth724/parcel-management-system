@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <x-page-header :title="$user->name"
                   :subtitle="$user->email"
                   :back="route('users.index')">
        <x-slot:actions>
            @can('update', $user)
                <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan

            @can('toggleStatus', $user)
                <form method="POST"
                      action="{{ route('users.toggle-status', $user) }}"
                      data-confirm="{{ $user->is_active ? 'Deactivate' : 'Reactivate' }} {{ $user->name }}'s account?">
                    @csrf
                    <button class="btn btn-outline-{{ $user->is_active ? 'warning' : 'success' }}">
                        <i class="bi bi-toggle-on me-1"></i>
                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($user->is_active)
        <div class="alert alert-secondary d-flex align-items-center gap-2">
            <i class="bi bi-slash-circle"></i>
            <div>This account is deactivated and cannot sign in.</div>
        </div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <img src="{{ $user->avatar_url }}" alt="" class="avatar avatar--lg mb-3">
                    <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
                    <div class="mb-3">
                        <x-status-badge :status="$user->role" />
                        <span class="status-badge status-badge--{{ $user->is_active ? 'success' : 'secondary' }}">
                            {{ $user->is_active ? 'Active' : 'Deactivated' }}
                        </span>
                    </div>

                    <dl class="row small text-start mb-0">
                        <dt class="col-5 text-muted fw-normal">Email</dt>
                        <dd class="col-7">
                            {{ $user->email }}
                            <div>
                                <small class="{{ $user->hasVerifiedEmail() ? 'text-success' : 'text-warning' }}">
                                    <i class="bi bi-{{ $user->hasVerifiedEmail() ? 'patch-check' : 'exclamation-circle' }}"></i>
                                    {{ $user->hasVerifiedEmail() ? 'verified' : 'not verified' }}
                                </small>
                            </div>
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Phone</dt>
                        <dd class="col-7">{{ $user->phone ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Branch</dt>
                        <dd class="col-7">
                            @if ($user->branch)
                                <a href="{{ route('branches.show', $user->branch) }}" class="text-decoration-none">
                                    {{ $user->branch->name }}
                                </a>
                            @else
                                All branches
                            @endif
                        </dd>

                        @if ($user->managedBranch)
                            <dt class="col-5 text-muted fw-normal">Manages</dt>
                            <dd class="col-7">{{ $user->managedBranch->name }}</dd>
                        @endif

                        @if ($user->driver)
                            <dt class="col-5 text-muted fw-normal">Driver record</dt>
                            <dd class="col-7">
                                <a href="{{ route('drivers.show', $user->driver) }}" class="text-decoration-none">
                                    {{ $user->driver->driver_code }}
                                </a>
                            </dd>
                        @endif

                        <dt class="col-5 text-muted fw-normal">Last sign-in</dt>
                        <dd class="col-7">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Created</dt>
                        <dd class="col-7">{{ $user->created_at->format('d M Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            {{-- Activity counters --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <x-stat-card label="Parcels booked"
                                 :value="number_format($activity['parcels_created'])"
                                 icon="bi-box-seam"
                                 variant="primary" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card label="Customers added"
                                 :value="number_format($activity['customers_created'])"
                                 icon="bi-people"
                                 variant="info" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card label="Tracking updates"
                                 :value="number_format($activity['tracking_updates'])"
                                 icon="bi-clock-history"
                                 variant="secondary" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card label="Assignments made"
                                 :value="number_format($activity['deliveries_assigned'])"
                                 icon="bi-person-check"
                                 variant="warning" />
                </div>
            </div>

            <div class="table-card">
                <div class="card-header bg-white border-0 p-3">
                    <h6 class="mb-0 fw-bold">Recently booked parcels</h6>
                    <small class="text-muted">Shipments this user created</small>
                </div>

                @if ($recentParcels->isEmpty())
                    <x-empty-state icon="bi-box-seam" title="No parcels booked by this user yet" />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tracking No.</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Booked</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentParcels as $parcel)
                                    <tr>
                                        <td>
                                            <a href="{{ route('parcels.show', $parcel) }}" class="tracking-code text-decoration-none small">
                                                {{ $parcel->tracking_number }}
                                            </a>
                                        </td>
                                        <td><small>{{ $parcel->customer?->full_name ?? '—' }}</small></td>
                                        <td><x-status-badge :status="$parcel->status" /></td>
                                        <td><small class="text-muted">{{ $parcel->created_at->format('d M Y') }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
