@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <x-page-header title="Reports"
                   subtitle="Every report can be filtered by date range and exported to CSV, Excel or PDF." />

    <div class="row g-3">
        @php
            $meta = [
                'daily-shipments' => ['bi-calendar-week', 'primary', 'Shipments booked each day with delivered, failed and returned counts, plus revenue.'],
                'monthly-revenue' => ['bi-cash-stack', 'success', 'Revenue collected, outstanding and refunded per month, split by payment method.'],
                'driver-performance' => ['bi-person-badge', 'warning', 'Assignments, completions, failures and success rate for every driver.'],
                'customer-shipments' => ['bi-people', 'info', 'Shipment volume and total spend for each customer.'],
                'deliveries' => ['bi-truck', 'secondary', 'Row-per-delivery detail including duration, receiver and cash collected.'],
            ];
        @endphp

        @foreach ($reportLinks as $report)
            @php [$icon, $variant, $description] = $meta[$report['key']]; @endphp

            <div class="col-md-6 col-xl-4">
                <a href="{{ route('reports.'.$report['key']) }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none text-reset">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-card__icon bg-{{ $variant }} bg-opacity-10 text-{{ $variant }}">
                                <i class="bi {{ $icon }}"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">{{ $report['title'] }}</h6>
                                <p class="small text-muted mb-0">{{ $description }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <span class="small text-primary fw-semibold">
                            Open report <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card border-0 bg-white shadow-sm mt-4">
        <div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-info-circle text-primary"></i> About these reports</h6>
            <ul class="small text-muted mb-0 ps-3">
                <li>Revenue figures count only parcels whose payment status is <strong>Paid</strong>.</li>
                <li>Success rate is completed deliveries as a share of completed plus failed attempts.</li>
                <li>
                    @if (auth()->user()->isSuperAdmin())
                        As a Super Admin you can report across every branch, or filter to one.
                    @else
                        Your reports are scoped to {{ auth()->user()->branch?->name ?? 'your branch' }}.
                    @endif
                </li>
                <li>PDF export uses landscape A4; CSV and Excel contain the same columns as the screen.</li>
            </ul>
        </div>
    </div>
@endsection
