@extends('layouts.app')

@section('title', $branch->name)

@section('content')
    <x-page-header :title="$branch->name"
                   :subtitle="$branch->code.' · '.$branch->full_address"
                   :back="route('branches.index')">
        <x-slot:actions>
            <a href="{{ route('branches.shipments', $branch) }}" class="btn btn-outline-secondary">
                <i class="bi bi-box-seam me-1"></i> Shipments
            </a>
            @can('update', $branch)
                <a href="{{ route('branches.edit', $branch) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <x-stat-card label="Total parcels"
                         :value="number_format($branch->parcels_count)"
                         icon="bi-box-seam"
                         variant="primary"
                         :meta="number_format($summary['today']).' booked today'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card label="Delivered"
                         :value="number_format($summary['delivered'])"
                         icon="bi-check2-circle"
                         variant="success"
                         :meta="number_format($summary['in_transit']).' in transit'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card label="Failed"
                         :value="number_format($summary['failed'])"
                         icon="bi-x-octagon"
                         variant="danger" />
        </div>
        @can('view-revenue')
            <div class="col-6 col-xl-3">
                <x-stat-card label="Revenue"
                             :value="'Rs. '.number_format($summary['revenue'], 2)"
                             icon="bi-cash-stack"
                             variant="info" />
            </div>
        @endcan
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Branch details</h6>
                    <span class="status-badge status-badge--{{ $branch->is_active ? 'success' : 'secondary' }}">
                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Code</dt>
                        <dd class="col-7 tracking-code">{{ $branch->code }}</dd>

                        <dt class="col-5 text-muted fw-normal">Address</dt>
                        <dd class="col-7">{{ $branch->full_address }}</dd>

                        <dt class="col-5 text-muted fw-normal">Contact</dt>
                        <dd class="col-7">
                            <a href="tel:{{ $branch->contact_number }}" class="text-decoration-none">
                                {{ $branch->contact_number }}
                            </a>
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Email</dt>
                        <dd class="col-7">{{ $branch->email ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Manager</dt>
                        <dd class="col-7">
                            @if ($branch->manager)
                                <a href="{{ route('users.show', $branch->manager) }}" class="text-decoration-none fw-semibold">
                                    {{ $branch->manager->name }}
                                </a>
                                <div><small class="text-muted">{{ $branch->manager->email }}</small></div>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Customers</dt>
                        <dd class="col-7">{{ number_format($branch->customers_count) }}</dd>

                        <dt class="col-5 text-muted fw-normal">Opened</dt>
                        <dd class="col-7">{{ $branch->created_at->format('d M Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Drivers assigned to this branch --}}
        <div class="col-lg-8">
            <div class="table-card mb-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-3">
                    <div>
                        <h6 class="mb-0 fw-bold">Drivers</h6>
                        <small class="text-muted">{{ $branch->drivers_count }} assigned to this branch</small>
                    </div>
                    @can('create', App\Models\Driver::class)
                        <a href="{{ route('drivers.create') }}" class="btn btn-sm btn-outline-primary">Add driver</a>
                    @endcan
                </div>

                @if ($drivers->isEmpty())
                    <x-empty-state icon="bi-person-badge" title="No drivers assigned yet" />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Phone</th>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($drivers as $driver)
                                    <tr>
                                        <td>
                                            <a href="{{ route('drivers.show', $driver) }}" class="text-decoration-none fw-semibold">
                                                {{ $driver->full_name }}
                                            </a>
                                            <div><small class="text-muted tracking-code">{{ $driver->driver_code }}</small></div>
                                        </td>
                                        <td><small>{{ $driver->phone }}</small></td>
                                        <td>
                                            <div class="small">{{ $driver->vehicle_number }}</div>
                                            <small class="text-muted">{{ $driver->vehicle_type->label() }}</small>
                                        </td>
                                        <td><x-status-badge :status="$driver->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Staff --}}
            <div class="table-card">
                <div class="card-header bg-white border-0 p-3">
                    <h6 class="mb-0 fw-bold">Staff accounts</h6>
                    <small class="text-muted">{{ $branch->staff_count }} people work at this branch</small>
                </div>

                @if ($staff->isEmpty())
                    <x-empty-state icon="bi-people" title="No staff assigned yet" />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($staff as $member)
                                    <tr>
                                        <td>
                                            @can('view', $member)
                                                <a href="{{ route('users.show', $member) }}" class="text-decoration-none fw-semibold">
                                                    {{ $member->name }}
                                                </a>
                                            @else
                                                <span class="fw-semibold">{{ $member->name }}</span>
                                            @endcan
                                        </td>
                                        <td><small class="text-muted">{{ $member->email }}</small></td>
                                        <td><x-status-badge :status="$member->role" /></td>
                                        <td>
                                            <span class="status-badge status-badge--{{ $member->is_active ? 'success' : 'secondary' }}">
                                                {{ $member->is_active ? 'Active' : 'Deactivated' }}
                                            </span>
                                        </td>
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
