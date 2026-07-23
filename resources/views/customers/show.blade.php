@extends('layouts.app')

@section('title', $customer->full_name)

@section('content')
    <x-page-header :title="$customer->full_name"
                   :subtitle="$customer->customer_code.($customer->company_name ? ' · '.$customer->company_name : '')"
                   :back="route('customers.index')">
        <x-slot:actions>
            @can('create', App\Models\Parcel::class)
                @if ($customer->can_book)
                    <a href="{{ route('parcels.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Book a parcel
                    </a>
                @endif
            @endcan

            @can('update', $customer)
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($customer->can_book)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                This customer is <strong>{{ $customer->status->label() }}</strong> and cannot book new shipments.
            </div>
        </div>
    @endunless

    {{-- Lifetime summary --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <x-stat-card label="Total shipments"
                         :value="number_format($summary['total'])"
                         icon="bi-box-seam"
                         variant="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card label="Delivered"
                         :value="number_format($summary['delivered'])"
                         icon="bi-check2-circle"
                         variant="success"
                         :meta="number_format($summary['in_transit']).' still in transit'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card label="Failed"
                         :value="number_format($summary['failed'])"
                         icon="bi-x-octagon"
                         variant="danger" />
        </div>
        @can('view-revenue')
            <div class="col-6 col-xl-3">
                <x-stat-card label="Lifetime spend"
                             :value="'Rs. '.number_format($summary['total_spend'], 2)"
                             icon="bi-cash-stack"
                             variant="info" />
            </div>
        @endcan
    </div>

    <div class="row g-4">
        {{-- Profile card --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Profile</h6>
                    <x-status-badge :status="$customer->status" />
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Customer ID</dt>
                        <dd class="col-7 tracking-code">{{ $customer->customer_code }}</dd>

                        <dt class="col-5 text-muted fw-normal">NIC / Passport</dt>
                        <dd class="col-7">{{ $customer->nic_passport }}</dd>

                        <dt class="col-5 text-muted fw-normal">Mobile</dt>
                        <dd class="col-7">
                            <a href="tel:{{ $customer->mobile }}" class="text-decoration-none">{{ $customer->mobile }}</a>
                        </dd>

                        <dt class="col-5 text-muted fw-normal">Email</dt>
                        <dd class="col-7">
                            @if ($customer->email)
                                <a href="mailto:{{ $customer->email }}" class="text-decoration-none">{{ $customer->email }}</a>
                            @else
                                —
                            @endif
                        </dd>

                        @if ($customer->company_name)
                            <dt class="col-5 text-muted fw-normal">Company</dt>
                            <dd class="col-7">{{ $customer->company_name }}</dd>
                        @endif

                        <dt class="col-5 text-muted fw-normal">Address</dt>
                        <dd class="col-7">{{ $customer->full_address }}</dd>

                        <dt class="col-5 text-muted fw-normal">Home branch</dt>
                        <dd class="col-7">{{ $customer->branch?->name ?? 'Shared (all branches)' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Registered</dt>
                        <dd class="col-7">{{ $customer->created_at->format('d M Y') }}</dd>

                        <dt class="col-5 text-muted fw-normal">Registered by</dt>
                        <dd class="col-7">{{ $customer->creator?->name ?? 'System' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Last shipment</dt>
                        <dd class="col-7">
                            {{ $summary['last_shipment']
                                ? \Illuminate\Support\Carbon::parse($summary['last_shipment'])->format('d M Y')
                                : '—' }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Shipment history --}}
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-3">
                    <div>
                        <h6 class="mb-0 fw-bold">Shipment history</h6>
                        <small class="text-muted">Every parcel booked by this customer</small>
                    </div>
                    <a href="{{ route('parcels.index', ['customer_id' => $customer->id]) }}"
                       class="btn btn-sm btn-outline-secondary">Open in parcels</a>
                </div>

                @if ($parcels->isEmpty())
                    <x-empty-state icon="bi-box-seam"
                                   title="No shipments yet"
                                   message="Parcels booked by this customer will appear here." />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tracking No.</th>
                                    <th>Destination</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    @can('view-revenue')
                                        <th class="text-end">Charge</th>
                                    @endcan
                                    <th>Booked</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($parcels as $parcel)
                                    <tr>
                                        <td>
                                            <a href="{{ route('parcels.show', $parcel) }}" class="tracking-code text-decoration-none small">
                                                {{ $parcel->tracking_number }}
                                            </a>
                                        </td>
                                        <td>
                                            <div>{{ $parcel->receiver_city }}</div>
                                            <small class="text-muted">{{ $parcel->receiver_name }}</small>
                                        </td>
                                        <td><x-status-badge :status="$parcel->priority" /></td>
                                        <td><x-status-badge :status="$parcel->status" /></td>
                                        @can('view-revenue')
                                            <td class="text-end">@money($parcel->delivery_charge)</td>
                                        @endcan
                                        <td><small class="text-muted">{{ $parcel->created_at->format('d M Y') }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 border-top">
                        {{ $parcels->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
