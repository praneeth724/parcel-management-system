@extends('layouts.app')

@section('title', 'Assign parcels')

@section('content')
    <x-page-header title="Assign parcels to drivers"
                   :subtitle="number_format($parcels->total()).' '.str('parcel')->plural($parcels->total()).' waiting — same-day and express first'" />

    <div class="row g-4">
        {{-- Available drivers, kept in view while assigning --}}
        <div class="col-lg-4 order-lg-2">
            <div class="card border-0 shadow-sm sticky-top" style="top: 5rem;">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-check text-success"></i> Available drivers</h6>
                    <small class="text-muted">{{ $drivers->count() }} ready to take a parcel</small>
                </div>
                <div class="card-body p-0">
                    @if ($drivers->isEmpty())
                        <x-empty-state icon="bi-person-x"
                                       title="No drivers available"
                                       message="Every driver is either out on delivery or off duty." />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($drivers as $driver)
                                <li class="list-group-item d-flex align-items-center gap-2">
                                    <img src="{{ $driver->photo_url }}" alt="" class="avatar avatar--sm">
                                    <div class="flex-grow-1 min-w-0">
                                        <a href="{{ route('drivers.show', $driver) }}" class="text-decoration-none fw-semibold small">
                                            {{ $driver->full_name }}
                                        </a>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            {{ $driver->vehicle_type->label() }} ·
                                            up to {{ number_format($driver->vehicle_type->capacityKg()) }} kg
                                        </div>
                                    </div>
                                    @if ($driver->active_deliveries_count > 0)
                                        <span class="badge text-bg-light">{{ $driver->active_deliveries_count }} open</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- The queue --}}
        <div class="col-lg-8 order-lg-1">
            <form method="GET" action="{{ route('deliveries.assign') }}" class="filter-bar">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label for="search" class="form-label small fw-semibold">Search</label>
                        <input type="search"
                               id="search"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               class="form-control"
                               placeholder="Tracking number, receiver or customer">
                    </div>

                    @if ($branches->count() > 1)
                        <div class="col-md-4">
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

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </div>
            </form>

            <div class="table-card">
                @if ($parcels->isEmpty())
                    <x-empty-state icon="bi-check2-all"
                                   title="Everything is assigned"
                                   message="No parcels are waiting for a driver right now." />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Parcel</th>
                                    <th>Destination</th>
                                    <th>Weight</th>
                                    <th>Waiting</th>
                                    <th class="text-end">Assign to</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($parcels as $parcel)
                                    <tr>
                                        <td>
                                            <a href="{{ route('parcels.show', $parcel) }}" class="tracking-code text-decoration-none small">
                                                {{ $parcel->tracking_number }}
                                            </a>
                                            <div>
                                                <x-status-badge :status="$parcel->priority" />
                                                <x-status-badge :status="$parcel->status" />
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold">{{ $parcel->receiver_city }}</div>
                                            <small class="text-muted">{{ $parcel->receiver_name }}</small>
                                        </td>
                                        <td>
                                            <small class="fw-semibold">{{ $parcel->chargeable_weight }} kg</small>
                                        </td>
                                        <td>
                                            <small class="{{ $parcel->is_overdue ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                {{ $parcel->created_at->diffForHumans(null, true) }}
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            @if ($drivers->isEmpty())
                                                <small class="text-muted">No drivers free</small>
                                            @else
                                                <form method="POST" action="{{ route('deliveries.store') }}" class="d-flex gap-1 justify-content-end">
                                                    @csrf
                                                    <input type="hidden" name="parcel_id" value="{{ $parcel->id }}">

                                                    <select name="driver_id" class="form-select form-select-sm" style="max-width: 12rem;" required>
                                                        <option value="">Choose driver…</option>
                                                        @foreach ($drivers as $driver)
                                                            <option value="{{ $driver->id }}"
                                                                    @disabled($parcel->chargeable_weight > $driver->vehicle_type->capacityKg())>
                                                                {{ $driver->full_name }}
                                                                @if ($parcel->chargeable_weight > $driver->vehicle_type->capacityKg())
                                                                    (too heavy)
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </select>

                                                    <button type="submit" class="btn btn-sm btn-primary" title="Assign">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 border-top">
                        {{ $parcels->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
