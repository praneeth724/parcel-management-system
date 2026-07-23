@extends('layouts.app')

@section('title', 'Super Admin Dashboard')

@section('content')
    <x-page-header title="Network overview"
                   :subtitle="'Every branch, every parcel — as of '.now()->format('l, d M Y H:i')">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
            </a>
            <a href="{{ route('parcels.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Book a parcel
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Headline counters --}}
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
            <x-stat-card label="Total revenue"
                         :value="'Rs. '.number_format($stats['total_revenue'], 2)"
                         icon="bi-cash-stack"
                         variant="success"
                         :meta="'Rs. '.number_format($stats['todays_revenue'], 2).' today'" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Available drivers"
                         :value="number_format($stats['available_drivers'])"
                         icon="bi-person-check"
                         variant="info"
                         :meta="'of '.number_format($stats['total_drivers']).' drivers'"
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
            <x-stat-card label="Registered customers"
                         :value="number_format($stats['total_customers'])"
                         icon="bi-people"
                         variant="secondary"
                         :meta="number_format($stats['unassigned_parcels']).' parcels awaiting a driver'"
                         :href="route('customers.index')" />
        </div>
    </div>

    @include('dashboard.partials.charts')

    {{-- Branch comparison --}}
    <div class="table-card mb-4">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-3">
            <div>
                <h6 class="mb-0 fw-bold">Branch performance</h6>
                <small class="text-muted">All-time totals per location</small>
            </div>
            <a href="{{ route('branches.index') }}" class="btn btn-sm btn-outline-secondary">Manage branches</a>
        </div>

        @if ($branchPerformance->isEmpty())
            <x-empty-state icon="bi-building" title="No branches configured yet">
                <a href="{{ route('branches.create') }}" class="btn btn-primary btn-sm">Add the first branch</a>
            </x-empty-state>
        @else
            <div class="table-card__scroll">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>City</th>
                            <th class="text-end">Parcels</th>
                            <th class="text-end">Delivered</th>
                            <th class="text-end">Failed</th>
                            <th class="text-end">Success rate</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($branchPerformance as $row)
                            @php
                                $finished = (int) $row->delivered + (int) $row->failed;
                                $rate = $finished === 0 ? 0 : round(($row->delivered / $finished) * 100, 1);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('branches.show', $row->id) }}" class="text-decoration-none fw-semibold">
                                        {{ $row->name }}
                                    </a>
                                    <div><small class="text-muted">{{ $row->code }}</small></div>
                                </td>
                                <td>{{ $row->city }}</td>
                                <td class="text-end">{{ number_format((int) $row->total_parcels) }}</td>
                                <td class="text-end text-success">{{ number_format((int) $row->delivered) }}</td>
                                <td class="text-end text-danger">{{ number_format((int) $row->failed) }}</td>
                                <td class="text-end">
                                    <span class="badge text-bg-{{ $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'secondary') }}">
                                        {{ $rate }}%
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">@money($row->revenue ?? 0)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('dashboard.partials.leaderboards')

    @include('dashboard.partials.recent-parcels')
@endsection
