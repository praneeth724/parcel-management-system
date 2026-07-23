{{--
    Shared chart block used by the Super Admin and Branch Manager dashboards.

    Expects: $monthlyShipments, $monthlyRevenue, $successRate — each already
    shaped as {labels: [], data: []} by DashboardService.
--}}

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold">Monthly shipments</h6>
                    <small class="text-muted">Parcels booked over the last 12 months</small>
                </div>
                <span class="badge text-bg-light">{{ array_sum($monthlyShipments['data']) }} total</span>
            </div>
            <div class="card-body">
                <div class="chart-shell chart-shell--md">
                    <canvas id="monthlyShipmentsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Delivery success rate</h6>
                <small class="text-muted">Share of finished parcels</small>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="chart-shell chart-shell--sm flex-grow-1">
                    <canvas id="successRateChart"></canvas>
                </div>
                <div class="text-center mt-3">
                    <div class="display-6 fw-bold text-success">{{ $successRate['success_rate'] }}%</div>
                    <small class="text-muted">delivered successfully</small>
                </div>
            </div>
        </div>
    </div>
</div>

@can('view-revenue')
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold">Monthly revenue</h6>
                <small class="text-muted">Settled delivery charges, last 12 months</small>
            </div>
            <span class="badge text-bg-success">@money(array_sum($monthlyRevenue['data']))</span>
        </div>
        <div class="card-body">
            <div class="chart-shell chart-shell--md">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>
    </div>
@endcan

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const shipments = @json($monthlyShipments);
    const success = @json($successRate);
    const revenue = @json($monthlyRevenue ?? ['labels' => [], 'data' => []]);

    new Chart(document.getElementById('monthlyShipmentsChart'), {
        type: 'line',
        data: {
            labels: shipments.labels,
            datasets: [{
                label: 'Shipments',
                data: shipments.data,
                borderColor: '#1a56db',
                backgroundColor: 'rgba(26, 86, 219, 0.12)',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointHoverRadius: 5,
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

    new Chart(document.getElementById('successRateChart'), {
        type: 'doughnut',
        data: {
            labels: success.labels,
            datasets: [{
                data: success.data,
                backgroundColor: success.colors,
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            cutout: '68%',
            plugins: { legend: { position: 'bottom' } },
        },
    });

    const revenueCanvas = document.getElementById('monthlyRevenueChart');
    if (revenueCanvas) {
        new Chart(revenueCanvas, {
            type: 'bar',
            data: {
                labels: revenue.labels,
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: revenue.data,
                    backgroundColor: 'rgba(15, 157, 88, 0.85)',
                    borderRadius: 6,
                    maxBarThickness: 44,
                }],
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => 'Rs. ' + Number(ctx.parsed.y).toLocaleString('en-LK', {
                                minimumFractionDigits: 2,
                            }),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(15,23,42,0.06)' },
                        ticks: { callback: (v) => 'Rs. ' + Number(v).toLocaleString('en-LK') },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
    }
});
</script>
@endpush
