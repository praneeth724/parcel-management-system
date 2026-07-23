@extends('layouts.app')

@section('title', 'Parcels')

@section('content')
    <x-page-header title="Parcels"
                   :subtitle="number_format($parcels->total()).' '.str('shipment')->plural($parcels->total()).' match your filters'">
        <x-slot:actions>
            @can('create', App\Models\Parcel::class)
                <a href="{{ route('parcels.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Book a parcel
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('parcels.index') }}" class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="search" class="form-label small fw-semibold">Search</label>
                <input type="search"
                       id="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       class="form-control"
                       placeholder="Tracking number, receiver, customer or phone">
            </div>

            <div class="col-6 col-md-2">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select" data-auto-submit>
                    <option value="">All</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="priority" class="form-label small fw-semibold">Priority</label>
                <select id="priority" name="priority" class="form-select" data-auto-submit>
                    <option value="">All</option>
                    @foreach ($priorities as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="driver_id" class="form-label small fw-semibold">Driver</label>
                <select id="driver_id" name="driver_id" class="form-select" data-auto-submit>
                    <option value="">Any driver</option>
                    @foreach ($drivers as $id => $label)
                        <option value="{{ $id }}" @selected((string) ($filters['driver_id'] ?? '') === (string) $id)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($branches->count() > 1)
                <div class="col-6 col-md-2">
                    <label for="branch_id" class="form-label small fw-semibold">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select" data-auto-submit>
                        <option value="">All</option>
                        @foreach ($branches as $id => $label)
                            <option value="{{ $id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-6 col-md-2">
                <label for="from" class="form-label small fw-semibold">Booked from</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>

            <div class="col-6 col-md-2">
                <label for="to" class="form-label small fw-semibold">Booked to</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="{{ route('parcels.index') }}" class="btn btn-outline-secondary" title="Clear filters">
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
                <label for="trashed" class="form-check-label small">Show archived parcels only</label>
            </div>
        @endcan
    </form>

    <div class="table-card">
        @include('parcels._table')
    </div>
@endsection
