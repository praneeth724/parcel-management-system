{{-- Expects: $topCustomers, $topDrivers --}}

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="table-card h-100">
            <div class="card-header bg-white border-0 p-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-trophy text-warning"></i> Top customers</h6>
                <small class="text-muted">Ranked by shipment volume</small>
            </div>

            @if ($topCustomers->isEmpty())
                <x-empty-state icon="bi-people" title="No customer activity yet" />
            @else
                <div class="table-card__scroll">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th class="text-end">Shipments</th>
                                @can('view-revenue')
                                    <th class="text-end">Revenue</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topCustomers as $index => $customer)
                                <tr>
                                    <td class="text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none fw-semibold">
                                            {{ $customer->full_name }}
                                        </a>
                                        <div><small class="text-muted">{{ $customer->customer_code }}</small></div>
                                    </td>
                                    <td class="text-end fw-semibold">{{ $customer->shipments_count }}</td>
                                    @can('view-revenue')
                                        <td class="text-end">@money($customer->revenue ?? 0)</td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="table-card h-100">
            <div class="card-header bg-white border-0 p-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-award text-success"></i> Top performing drivers</h6>
                <small class="text-muted">Ranked by completed deliveries</small>
            </div>

            @if ($topDrivers->isEmpty())
                <x-empty-state icon="bi-person-badge" title="No completed deliveries yet" />
            @else
                <div class="table-card__scroll">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Driver</th>
                                <th class="text-end">Completed</th>
                                <th class="text-end">Success</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topDrivers as $index => $driver)
                                <tr>
                                    <td class="text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $driver->photo_url }}" alt="" class="avatar avatar--sm">
                                            <div>
                                                <a href="{{ route('drivers.show', $driver) }}" class="text-decoration-none fw-semibold">
                                                    {{ $driver->full_name }}
                                                </a>
                                                <div><small class="text-muted">{{ $driver->branch?->name ?? $driver->driver_code }}</small></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold">{{ $driver->completed_deliveries_count }}</td>
                                    <td class="text-end">
                                        <span class="badge text-bg-{{ $driver->success_rate >= 90 ? 'success' : ($driver->success_rate >= 70 ? 'warning' : 'danger') }}">
                                            {{ $driver->success_rate }}%
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
</div>
