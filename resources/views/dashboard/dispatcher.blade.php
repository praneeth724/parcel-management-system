@extends('layouts.app')

@section('title', 'Dispatcher Dashboard')

@section('content')
    <x-page-header title="Dispatch board"
                   :subtitle="'Your workload for '.now()->format('l, d M Y')">
        <x-slot:actions>
            <a href="{{ route('deliveries.assign') }}" class="btn btn-outline-primary">
                <i class="bi bi-person-check me-1"></i> Assign parcels
            </a>
            <a href="{{ route('parcels.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Book a parcel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Awaiting a driver"
                         :value="number_format($stats['unassigned_parcels'])"
                         icon="bi-inbox"
                         variant="danger"
                         meta="Needs your attention now"
                         :href="route('deliveries.assign')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Booked today"
                         :value="number_format($stats['todays_shipments'])"
                         icon="bi-box-seam"
                         variant="primary"
                         :meta="number_format($stats['pending_deliveries']).' in transit'"
                         :href="route('parcels.index')" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Available drivers"
                         :value="number_format($stats['available_drivers'])"
                         icon="bi-person-check"
                         variant="success"
                         :meta="number_format($stats['drivers_on_delivery']).' out on delivery'" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Overdue parcels"
                         :value="number_format($stats['overdue_parcels'])"
                         icon="bi-alarm"
                         variant="warning"
                         meta="Past the promised delivery date" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- The dispatcher's actual queue --}}
        <div class="col-xl-7">
            <div class="table-card h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-3">
                    <div>
                        <h6 class="mb-0 fw-bold">Parcels waiting for a driver</h6>
                        <small class="text-muted">Same-day first, then express, then oldest</small>
                    </div>
                    <a href="{{ route('deliveries.assign') }}" class="btn btn-sm btn-primary">Assign</a>
                </div>

                @if ($unassignedParcels->isEmpty())
                    <x-empty-state icon="bi-check2-all"
                                   title="Everything is assigned"
                                   message="No parcels are waiting for a driver right now." />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tracking No.</th>
                                    <th>Destination</th>
                                    <th>Priority</th>
                                    <th>Waiting</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unassignedParcels as $parcel)
                                    <tr>
                                        <td>
                                            <a href="{{ route('parcels.show', $parcel) }}" class="tracking-code text-decoration-none">
                                                {{ $parcel->tracking_number }}
                                            </a>
                                            <div><small class="text-muted">{{ $parcel->customer?->full_name }}</small></div>
                                        </td>
                                        <td>{{ $parcel->receiver_city }}</td>
                                        <td><x-status-badge :status="$parcel->priority" /></td>
                                        <td>
                                            <span class="{{ $parcel->is_overdue ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                {{ $parcel->created_at->diffForHumans(null, true) }}
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

        {{-- Assignments the driver has not responded to --}}
        <div class="col-xl-5">
            <div class="table-card h-100">
                <div class="card-header bg-white border-0 p-3">
                    <h6 class="mb-0 fw-bold">Awaiting driver response</h6>
                    <small class="text-muted">Assigned but not yet accepted</small>
                </div>

                @if ($awaitingResponse->isEmpty())
                    <x-empty-state icon="bi-hand-thumbs-up"
                                   title="All caught up"
                                   message="Every assignment has been answered." />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Parcel</th>
                                    <th>Driver</th>
                                    <th>Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($awaitingResponse as $delivery)
                                    <tr>
                                        <td>
                                            <a href="{{ route('deliveries.show', $delivery) }}" class="tracking-code text-decoration-none small">
                                                {{ $delivery->parcel?->tracking_number }}
                                            </a>
                                        </td>
                                        <td>
                                            <small class="fw-semibold">{{ $delivery->driver?->full_name }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $delivery->assigned_at?->diffForHumans() }}</small>
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

    {{-- 14-day booking trend --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0 fw-bold">Shipments booked, last 14 days</h6>
        </div>
        <div class="card-body">
            <div class="chart-shell chart-shell--sm">
                <canvas id="dailyShipmentsChart"></canvas>
            </div>
        </div>
    </div>

    @include('dashboard.partials.recent-parcels')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const daily = @json($dailyShipments);

    new Chart(document.getElementById('dailyShipmentsChart'), {
        type: 'bar',
        data: {
            labels: daily.labels,
            datasets: [{
                label: 'Shipments',
                data: daily.data,
                backgroundColor: 'rgba(26, 86, 219, 0.85)',
                borderRadius: 6,
                maxBarThickness: 32,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.06)' } },
                x: { grid: { display: false } },
            },
        },
    });
});
</script>
@endpush
