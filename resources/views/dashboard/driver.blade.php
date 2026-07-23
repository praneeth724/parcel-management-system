@extends('layouts.app')

@section('title', 'Driver Dashboard')

@section('content')
    @if ($driver === null)
        {{-- The account has the Driver role but no driver record, so there is
             nothing to show until an administrator links the two. --}}
        <x-page-header title="Driver dashboard" />

        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Your driver profile is not set up yet.</strong>
                <p class="mb-0 small">
                    Your account has the Driver role but is not linked to a driver record,
                    so deliveries cannot be assigned to you. Please ask your Branch Manager
                    to link your account on the Drivers screen.
                </p>
            </div>
        </div>
    @else
        <x-page-header :title="'Hello, '.explode(' ', $driver->full_name)[0]"
                       :subtitle="$driver->driver_code.' · '.$driver->vehicle_number.' · '.$driver->vehicle_type->label()">
            <x-slot:actions>
                <x-status-badge :status="$driver->status" class="align-self-center" />
                <a href="{{ route('deliveries.index') }}" class="btn btn-primary">
                    <i class="bi bi-list-check me-1"></i> All my deliveries
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <x-stat-card label="New assignments"
                             :value="number_format($stats['pending_response'])"
                             icon="bi-bell"
                             variant="danger"
                             meta="Waiting for you to accept" />
            </div>
            <div class="col-6 col-xl-3">
                <x-stat-card label="On the road"
                             :value="number_format($stats['in_transit'])"
                             icon="bi-truck"
                             variant="warning"
                             :meta="number_format($stats['accepted']).' accepted, not started'" />
            </div>
            <div class="col-6 col-xl-3">
                <x-stat-card label="Delivered today"
                             :value="number_format($stats['completed_today'])"
                             icon="bi-check2-circle"
                             variant="success"
                             :meta="number_format($stats['completed_this_month']).' this month'" />
            </div>
            <div class="col-6 col-xl-3">
                <x-stat-card label="Success rate"
                             :value="$stats['success_rate'].'%'"
                             icon="bi-graph-up-arrow"
                             variant="info"
                             :meta="number_format($stats['completed_total']).' completed all time'" />
            </div>
        </div>

        @if ($stats['cod_collected_today'] > 0)
            <div class="alert alert-info d-flex align-items-center gap-2">
                <i class="bi bi-cash-coin"></i>
                <div>
                    You have collected <strong>@money($stats['cod_collected_today'])</strong>
                    in cash on delivery today. Remember to hand it in at the branch.
                </div>
            </div>
        @endif

        {{-- Active jobs, the main thing a driver looks at --}}
        <div class="table-card mb-4">
            <div class="card-header bg-white border-0 p-3">
                <h6 class="mb-0 fw-bold">My active deliveries</h6>
                <small class="text-muted">Accept a job, then mark it in transit when you set off</small>
            </div>

            @if ($activeDeliveries->isEmpty())
                <x-empty-state icon="bi-cup-hot"
                               title="No active deliveries"
                               message="You're all clear. New assignments will appear here." />
            @else
                <div class="table-card__scroll">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tracking No.</th>
                                <th>Receiver</th>
                                <th>Destination</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activeDeliveries as $delivery)
                                @php $parcel = $delivery->parcel; @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('deliveries.show', $delivery) }}" class="tracking-code text-decoration-none">
                                            {{ $parcel?->tracking_number }}
                                        </a>
                                        <div><x-status-badge :status="$parcel->priority" /></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $parcel?->receiver_name }}</div>
                                        <a href="tel:{{ $parcel?->receiver_phone }}" class="small text-decoration-none">
                                            <i class="bi bi-telephone"></i> {{ $parcel?->receiver_phone }}
                                        </a>
                                    </td>
                                    <td>
                                        <div>{{ $parcel?->receiver_city }}</div>
                                        <small class="text-muted">{{ Str::limit($parcel?->receiver_address, 40) }}</small>
                                    </td>
                                    <td>
                                        @if ($parcel?->payment_method->isCollectedOnDelivery())
                                            <span class="badge text-bg-warning">
                                                Collect @money($parcel->cod_amount ?: $parcel->delivery_charge)
                                            </span>
                                        @else
                                            <span class="badge text-bg-success">Prepaid</span>
                                        @endif
                                    </td>
                                    <td><x-status-badge :status="$delivery->status" /></td>
                                    <td class="text-end">
                                        <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-sm btn-primary">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold">Deliveries completed, last 14 days</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-shell chart-shell--sm">
                            <canvas id="driverPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="table-card h-100">
                    <div class="card-header bg-white border-0 p-3">
                        <h6 class="mb-0 fw-bold">Recently closed</h6>
                    </div>

                    @if ($recentDeliveries->isEmpty())
                        <x-empty-state icon="bi-clock-history" title="Nothing completed yet" />
                    @else
                        <div class="table-card__scroll">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Parcel</th>
                                        <th>Outcome</th>
                                        <th>When</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentDeliveries as $delivery)
                                        <tr>
                                            <td>
                                                <a href="{{ route('deliveries.show', $delivery) }}" class="tracking-code small text-decoration-none">
                                                    {{ $delivery->parcel?->tracking_number }}
                                                </a>
                                                <div><small class="text-muted">{{ $delivery->parcel?->receiver_city }}</small></div>
                                            </td>
                                            <td><x-status-badge :status="$delivery->status" /></td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ ($delivery->completed_at ?? $delivery->failed_at)?->diffForHumans() }}
                                                </small>
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
    @endif
@endsection

@push('scripts')
@if ($driver !== null)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const perf = @json($performance);

    new Chart(document.getElementById('driverPerformanceChart'), {
        type: 'bar',
        data: {
            labels: perf.labels,
            datasets: [{
                label: 'Completed',
                data: perf.data,
                backgroundColor: 'rgba(15, 157, 88, 0.85)',
                borderRadius: 6,
                maxBarThickness: 30,
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
@endif
@endpush
