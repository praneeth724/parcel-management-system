@extends('layouts.app')

@section('title', 'Drivers')

@section('content')
    <x-page-header title="Drivers"
                   :subtitle="$drivers->total().' '.str('driver')->plural($drivers->total()).' on record'">
        <x-slot:actions>
            @can('create', App\Models\Driver::class)
                <a href="{{ route('drivers.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> New driver
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('drivers.index') }}" class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="search" class="form-label small fw-semibold">Search</label>
                <input type="search"
                       id="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       class="form-control"
                       placeholder="Name, code, phone, vehicle or licence">
            </div>

            <div class="col-6 col-md-3">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($branches->count() > 1)
                <div class="col-6 col-md-3">
                    <label for="branch_id" class="form-label small fw-semibold">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select" data-auto-submit>
                        <option value="">All branches</option>
                        @foreach ($branches as $id => $label)
                            <option value="{{ $id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="{{ route('drivers.index') }}" class="btn btn-outline-secondary" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>

        @can('view-trash')
            <div class="form-check mt-3">
                <input type="checkbox"
                       name="trashed"
                       id="trashed"
                       value="1"
                       class="form-check-input"
                       data-auto-submit
                       @checked($filters['trashed'] ?? false)>
                <label for="trashed" class="form-check-label small">Show archived drivers only</label>
            </div>
        @endcan
    </form>

    <div class="table-card">
        @if ($drivers->isEmpty())
            <x-empty-state icon="bi-person-badge"
                           title="No drivers found"
                           message="Try widening your filters, or add a driver.">
                @can('create', App\Models\Driver::class)
                    <a href="{{ route('drivers.create') }}" class="btn btn-primary btn-sm">Add a driver</a>
                @endcan
            </x-empty-state>
        @else
            <div class="table-card__scroll">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Contact</th>
                            <th>Vehicle</th>
                            <th>Branch</th>
                            <th class="text-end">Active</th>
                            <th class="text-end">Success</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($drivers as $driver)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $driver->photo_url }}" alt="" class="avatar avatar--sm">
                                        <div>
                                            <a href="{{ route('drivers.show', $driver) }}" class="text-decoration-none fw-semibold">
                                                {{ $driver->full_name }}
                                            </a>
                                            <div><small class="text-muted tracking-code">{{ $driver->driver_code }}</small></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="tel:{{ $driver->phone }}" class="text-decoration-none small">{{ $driver->phone }}</a>
                                    @if ($driver->user)
                                        <div>
                                            <small class="badge text-bg-{{ $driver->user->is_active ? 'success' : 'secondary' }}">
                                                <i class="bi bi-person-check"></i> Has login
                                            </small>
                                        </div>
                                    @else
                                        <div><small class="text-muted">No login account</small></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold small">{{ $driver->vehicle_number }}</div>
                                    <small class="text-muted">{{ $driver->vehicle_type->label() }}</small>
                                    @if ($driver->license_has_expired)
                                        <div><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Licence expired</small></div>
                                    @endif
                                </td>
                                <td><small>{{ $driver->branch?->name ?? '—' }}</small></td>
                                <td class="text-end">{{ $driver->active_deliveries_count }}</td>
                                <td class="text-end">
                                    @if ($driver->completed_deliveries_count + $driver->failed_deliveries_count > 0)
                                        <span class="badge text-bg-{{ $driver->success_rate >= 90 ? 'success' : ($driver->success_rate >= 70 ? 'warning' : 'danger') }}">
                                            {{ $driver->success_rate }}%
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><x-status-badge :status="$driver->status" /></td>
                                <td>
                                    <div class="table-actions">
                                        @if ($driver->trashed())
                                            @can('restore', $driver)
                                                <form method="POST" action="{{ route('drivers.restore', $driver) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-success" title="Restore">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @else
                                            <a href="{{ route('drivers.show', $driver) }}"
                                               class="btn btn-sm btn-outline-secondary" title="View profile">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            @can('update', $driver)
                                                <a href="{{ route('drivers.edit', $driver) }}"
                                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan

                                            @can('toggleStatus', $driver)
                                                <form method="POST"
                                                      action="{{ route('drivers.toggle-status', $driver) }}"
                                                      data-confirm="{{ $driver->status === App\Enums\DriverStatus::Inactive ? 'Reactivate' : 'Deactivate' }} {{ $driver->full_name }}?">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-{{ $driver->status === App\Enums\DriverStatus::Inactive ? 'success' : 'warning' }}"
                                                            title="{{ $driver->status === App\Enums\DriverStatus::Inactive ? 'Activate' : 'Deactivate' }}">
                                                        <i class="bi bi-{{ $driver->status === App\Enums\DriverStatus::Inactive ? 'toggle-off' : 'toggle-on' }}"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top">
                {{ $drivers->links() }}
            </div>
        @endif
    </div>
@endsection
