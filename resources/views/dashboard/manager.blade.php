@extends('layouts.app')

@section('title', 'Branch Manager Dashboard')

@section('content')
    <x-page-header :title="$branch?->name ? $branch->name.' branch' : 'Branch overview'"
                   :subtitle="$branch?->full_address ?? 'Performance for the branch you manage'">
        <x-slot:actions>
            @if ($branch)
                <a href="{{ route('branches.shipments', $branch) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-box-seam me-1"></i> Branch shipments
                </a>
            @endif
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
            </a>
            <a href="{{ route('parcels.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Book a parcel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Today's shipments"
                         :value="number_format($stats['todays_shipments'])"
                         icon="bi-box-seam"
                         variant="primary"
                         :meta="number_format($stats['total_parcels']).' all time'"
                         :href="route('parcels.index')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Today's deliveries"
                         :value="number_format($stats['todays_deliveries'])"
                         icon="bi-check2-circle"
                         variant="success"
                         :meta="number_format($stats['delivered_parcels']).' delivered in total'" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Pending deliveries"
                         :value="number_format($stats['pending_deliveries'])"
                         icon="bi-hourglass-split"
                         variant="warning"
                         :meta="$stats['overdue_parcels'].' past the promised date'" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Failed deliveries"
                         :value="number_format($stats['failed_deliveries'])"
                         icon="bi-x-octagon"
                         variant="danger"
                         :meta="number_format($stats['returned_parcels']).' returned to sender'" />
        </div>

        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Branch revenue"
                         :value="'Rs. '.number_format($stats['total_revenue'], 2)"
                         icon="bi-cash-stack"
                         variant="success"
                         :meta="'Rs. '.number_format($stats['pending_cod'], 2).' still to collect'" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Available drivers"
                         :value="number_format($stats['available_drivers'])"
                         icon="bi-person-check"
                         variant="info"
                         :meta="'of '.number_format($stats['total_drivers']).' in this branch'"
                         :href="route('drivers.index')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Drivers on delivery"
                         :value="number_format($stats['drivers_on_delivery'])"
                         icon="bi-truck"
                         variant="warning"
                         meta="Currently out on the road" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Awaiting a driver"
                         :value="number_format($stats['unassigned_parcels'])"
                         icon="bi-inbox"
                         variant="dark"
                         meta="Parcels ready to dispatch"
                         :href="route('deliveries.assign')" />
        </div>
    </div>

    @include('dashboard.partials.charts')

    {{-- Status breakdown --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0 fw-bold">Parcels by status</h6>
            <small class="text-muted">Where every shipment in this branch currently sits</small>
        </div>
        <div class="card-body">
            <div class="row g-2">
                @foreach ($statusBreakdown as $status)
                    <div class="col-6 col-md-4 col-xl-3">
                        <a href="{{ route('parcels.index', ['status' => $status['value']]) }}"
                           class="d-flex justify-content-between align-items-center p-2 rounded text-decoration-none text-reset border">
                            <span class="status-badge status-badge--{{ $status['color'] }}">{{ $status['label'] }}</span>
                            <span class="fw-bold">{{ number_format($status['count']) }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @include('dashboard.partials.leaderboards')

    @include('dashboard.partials.recent-parcels')
@endsection
