@extends('layouts.app')

@section('title', auth()->user()->isDriver() ? 'My deliveries' : 'Deliveries')

@section('content')
    <x-page-header :title="auth()->user()->isDriver() ? 'My deliveries' : 'Deliveries'"
                   :subtitle="number_format($deliveries->total()).' '.str('assignment')->plural($deliveries->total()).' match your filters'">
        <x-slot:actions>
            @can('assign-deliveries')
                <a href="{{ route('deliveries.assign') }}" class="btn btn-primary">
                    <i class="bi bi-person-check me-1"></i> Assign parcels
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('deliveries.index') }}" class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($drivers->isNotEmpty())
                <div class="col-6 col-md-3">
                    <label for="driver_id" class="form-label small fw-semibold">Driver</label>
                    <select id="driver_id" name="driver_id" class="form-select" data-auto-submit>
                        <option value="">All drivers</option>
                        @foreach ($drivers as $id => $label)
                            <option value="{{ $id }}" @selected((string) ($filters['driver_id'] ?? '') === (string) $id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-6 col-md-2">
                <label for="from" class="form-label small fw-semibold">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>

            <div class="col-6 col-md-2">
                <label for="to" class="form-label small fw-semibold">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="table-card">
        @if ($deliveries->isEmpty())
            <x-empty-state icon="bi-truck"
                           title="No deliveries found"
                           message="Assignments will appear here once parcels are handed to drivers." />
        @else
            <div class="table-card__scroll">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Receiver</th>
                            <th>Destination</th>
                            @unless (auth()->user()->isDriver())
                                <th>Driver</th>
                            @endunless
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            @php $parcel = $delivery->parcel; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('deliveries.show', $delivery) }}" class="tracking-code text-decoration-none small">
                                        {{ $parcel?->tracking_number ?? '—' }}
                                    </a>
                                    @if ($delivery->attempt_number > 1)
                                        <div><small class="text-muted">attempt {{ $delivery->attempt_number }}</small></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $parcel?->receiver_name }}</div>
                                    <a href="tel:{{ $parcel?->receiver_phone }}" class="small text-decoration-none">
                                        {{ $parcel?->receiver_phone }}
                                    </a>
                                </td>
                                <td>
                                    <div class="small">{{ $parcel?->receiver_city }}</div>
                                    @if ($parcel)
                                        <x-status-badge :status="$parcel->priority" />
                                    @endif
                                </td>
                                @unless (auth()->user()->isDriver())
                                    <td>
                                        <small class="fw-semibold">{{ $delivery->driver?->full_name }}</small>
                                        <div><small class="text-muted">{{ $delivery->driver?->driver_code }}</small></div>
                                    </td>
                                @endunless
                                <td>
                                    @if ($parcel?->payment_method->isCollectedOnDelivery())
                                        <span class="badge text-bg-warning">
                                            COD @money($parcel->cod_amount ?: $parcel->delivery_charge)
                                        </span>
                                    @else
                                        <span class="badge text-bg-success">Prepaid</span>
                                    @endif
                                </td>
                                <td><x-status-badge :status="$delivery->status" /></td>
                                <td><small class="text-muted">{{ $delivery->assigned_at?->format('d M H:i') }}</small></td>
                                <td class="text-end">
                                    <a href="{{ route('deliveries.show', $delivery) }}"
                                       class="btn btn-sm btn-{{ $delivery->is_open && auth()->user()->isDriver() ? 'primary' : 'outline-secondary' }}">
                                        {{ $delivery->is_open && auth()->user()->isDriver() ? 'Open' : 'View' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top">
                {{ $deliveries->links() }}
            </div>
        @endif
    </div>
@endsection
