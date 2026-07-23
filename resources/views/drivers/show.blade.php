@extends('layouts.app')

@section('title', $driver->full_name)

@section('content')
    <x-page-header :title="$driver->full_name"
                   :subtitle="$driver->driver_code.' · '.$driver->vehicle_number.' · '.$driver->vehicle_type->label()"
                   :back="route('drivers.index')">
        <x-slot:actions>
            @can('update', $driver)
                <a href="{{ route('drivers.edit', $driver) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan

            @can('toggleStatus', $driver)
                <form method="POST"
                      action="{{ route('drivers.toggle-status', $driver) }}"
                      data-confirm="{{ $driver->status === App\Enums\DriverStatus::Inactive ? 'Reactivate' : 'Deactivate' }} {{ $driver->full_name }}?">
                    @csrf
                    <button class="btn btn-outline-{{ $driver->status === App\Enums\DriverStatus::Inactive ? 'success' : 'warning' }}">
                        <i class="bi bi-toggle-on me-1"></i>
                        {{ $driver->status === App\Enums\DriverStatus::Inactive ? 'Activate' : 'Deactivate' }}
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($driver->license_has_expired)
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                This driver's licence expired on <strong>{{ $driver->license_expiry->format('d M Y') }}</strong>.
                They cannot be assigned new parcels until it is renewed.
            </div>
        </div>
    @endif

    {{-- Performance summary --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <x-stat-card label="Completed"
                         :value="number_format($performance['completed'])"
                         icon="bi-check2-circle"
                         variant="success"
                         :meta="number_format($performance['completed_this_month']).' this month'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card label="Success rate"
                         :value="$performance['success_rate'].'%'"
                         icon="bi-graph-up-arrow"
                         variant="info"
                         :meta="number_format($performance['total_assignments']).' assignments total'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card label="Failed / rejected"
                         :value="number_format($performance['failed']).' / '.number_format($performance['rejected'])"
                         icon="bi-x-octagon"
                         variant="danger" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card label="Average time"
                         :value="$performance['average_minutes'] ? $performance['average_minutes'].' min' : '—'"
                         icon="bi-stopwatch"
                         variant="warning"
                         meta="From accepting to completing" />
        </div>
    </div>

    <div class="row g-4">
        {{-- Profile --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <img src="{{ $driver->photo_url }}" alt="" class="avatar avatar--lg mb-3">
                    <h5 class="fw-bold mb-1">{{ $driver->full_name }}</h5>
                    <div class="mb-3"><x-status-badge :status="$driver->status" /></div>

                    <dl class="row small text-start mb-0">
                        <dt class="col-5 text-muted fw-normal">Driver code</dt>
                        <dd class="col-7 tracking-code">{{ $driver->driver_code }}</dd>

                        <dt class="col-5 text-muted fw-normal">Phone</dt>
                        <dd class="col-7">
                            <a href="tel:{{ $driver->phone }}" class="text-decoration-none">{{ $driver->phone }}</a>
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Email</dt>
                        <dd class="col-7">{{ $driver->email ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Branch</dt>
                        <dd class="col-7">{{ $driver->branch?->name ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Vehicle</dt>
                        <dd class="col-7">{{ $driver->vehicle_number }}</dd>

                        <dt class="col-5 text-muted fw-normal">Type</dt>
                        <dd class="col-7">
                            {{ $driver->vehicle_type->label() }}
                            <div><small class="text-muted">up to {{ number_format($driver->vehicle_type->capacityKg()) }} kg</small></div>
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Licence</dt>
                        <dd class="col-7">
                            {{ $driver->license_number }}
                            @if ($driver->license_expiry)
                                <div>
                                    <small class="{{ $driver->license_has_expired ? 'text-danger' : 'text-muted' }}">
                                        expires {{ $driver->license_expiry->format('d M Y') }}
                                    </small>
                                </div>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Login</dt>
                        <dd class="col-7">
                            @if ($driver->user)
                                {{ $driver->user->email }}
                                <div>
                                    <small class="text-muted">
                                        last seen {{ $driver->user->last_login_at?->diffForHumans() ?? 'never' }}
                                    </small>
                                </div>
                            @else
                                <span class="text-muted">Not linked</span>
                            @endif
                        </dd>

                        @can('view-revenue')
                            <dt class="col-5 text-muted fw-normal">COD collected</dt>
                            <dd class="col-7">@money($performance['cod_collected'])</dd>
                        @endcan
                    </dl>

                    @if ($driver->notes)
                        <hr>
                        <p class="small text-muted text-start mb-0">
                            <i class="bi bi-sticky"></i> {{ $driver->notes }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Assigned deliveries --}}
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header bg-white border-0 p-3">
                    <h6 class="mb-0 fw-bold">Assigned deliveries</h6>
                    <small class="text-muted">{{ $performance['active'] }} currently open</small>
                </div>

                @if ($deliveries->isEmpty())
                    <x-empty-state icon="bi-truck"
                                   title="No deliveries yet"
                                   message="Parcels assigned to this driver will appear here." />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tracking No.</th>
                                    <th>Destination</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($deliveries as $delivery)
                                    <tr>
                                        <td>
                                            <a href="{{ route('deliveries.show', $delivery) }}" class="tracking-code text-decoration-none small">
                                                {{ $delivery->parcel?->tracking_number ?? '—' }}
                                            </a>
                                            @if ($delivery->attempt_number > 1)
                                                <div><small class="text-muted">attempt {{ $delivery->attempt_number }}</small></div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $delivery->parcel?->receiver_city }}</div>
                                            <small class="text-muted">{{ $delivery->parcel?->receiver_name }}</small>
                                        </td>
                                        <td><x-status-badge :status="$delivery->status" /></td>
                                        <td><small class="text-muted">{{ $delivery->assigned_at?->format('d M Y H:i') }}</small></td>
                                        <td><small>{{ $delivery->duration_for_humans }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 border-top">
                        {{ $deliveries->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
