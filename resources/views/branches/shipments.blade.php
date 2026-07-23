@extends('layouts.app')

@section('title', $branch->name.' shipments')

@section('content')
    <x-page-header :title="$branch->name.' shipments'"
                   :subtitle="$parcels->total().' '.str('parcel')->plural($parcels->total()).' handled by this branch'"
                   :back="route('branches.show', $branch)" />

    <form method="GET" action="{{ route('branches.shipments', $branch) }}" class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="search" class="form-label small fw-semibold">Search</label>
                <input type="search"
                       id="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       class="form-control"
                       placeholder="Tracking number, receiver or customer">
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

            <div class="col-6 col-md-2">
                <label for="from" class="form-label small fw-semibold">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>

            <div class="col-6 col-md-2">
                <label for="to" class="form-label small fw-semibold">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>

            <div class="col-6 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" title="Filter">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </div>
    </form>

    <div class="table-card">
        @include('parcels._table', ['showBranch' => false])
    </div>
@endsection
