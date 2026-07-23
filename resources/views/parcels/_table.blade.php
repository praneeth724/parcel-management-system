{{--
    Shared parcel listing table.
    Expects: $parcels (paginator). Optional: $showBranch (bool, default true)
--}}

@php $showBranch = $showBranch ?? true; @endphp

@if ($parcels->isEmpty())
    <x-empty-state icon="bi-box-seam"
                   title="No parcels found"
                   message="Try widening your filters, or book a new shipment.">
        @can('create', App\Models\Parcel::class)
            <a href="{{ route('parcels.create') }}" class="btn btn-primary btn-sm">Book a parcel</a>
        @endcan
    </x-empty-state>
@else
    <div class="table-card__scroll">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Customer</th>
                    <th>Receiver</th>
                    <th>Driver</th>
                    @if ($showBranch)
                        <th>Branch</th>
                    @endif
                    <th>Priority</th>
                    <th>Status</th>
                    @can('view-revenue')
                        <th class="text-end">Charge</th>
                    @endcan
                    <th>Booked</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($parcels as $parcel)
                    <tr>
                        <td>
                            <a href="{{ route('parcels.show', $parcel) }}" class="tracking-code text-decoration-none small">
                                {{ $parcel->tracking_number }}
                            </a>
                            @if ($parcel->is_overdue)
                                <div>
                                    <small class="text-danger">
                                        <i class="bi bi-alarm"></i> overdue
                                    </small>
                                </div>
                            @endif
                        </td>

                        <td>
                            <div class="fw-semibold small">{{ $parcel->customer?->full_name ?? '—' }}</div>
                            <small class="text-muted">{{ $parcel->customer?->customer_code }}</small>
                        </td>

                        <td>
                            <div class="small">{{ $parcel->receiver_name }}</div>
                            <small class="text-muted">{{ $parcel->receiver_city }}</small>
                        </td>

                        <td>
                            @if ($parcel->activeDelivery?->driver)
                                <small class="fw-semibold">{{ $parcel->activeDelivery->driver->full_name }}</small>
                            @else
                                <small class="text-muted">Unassigned</small>
                            @endif
                        </td>

                        @if ($showBranch)
                            <td><small>{{ $parcel->branch?->name ?? '—' }}</small></td>
                        @endif

                        <td><x-status-badge :status="$parcel->priority" /></td>
                        <td><x-status-badge :status="$parcel->status" /></td>

                        @can('view-revenue')
                            <td class="text-end">
                                @money($parcel->delivery_charge)
                                <div><x-status-badge :status="$parcel->payment_status" /></div>
                            </td>
                        @endcan

                        <td>
                            <small class="text-muted" data-bs-toggle="tooltip"
                                   title="{{ $parcel->created_at->format('d M Y, H:i') }}">
                                {{ $parcel->created_at->format('d M Y') }}
                            </small>
                        </td>

                        <td>
                            <div class="table-actions">
                                <a href="{{ route('parcels.show', $parcel) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @can('printLabel', $parcel)
                                    <a href="{{ route('parcels.label', $parcel) }}"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-secondary" title="Print label">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                @endcan

                                @can('update', $parcel)
                                    <a href="{{ route('parcels.edit', $parcel) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="p-3 border-top">
        {{ $parcels->links() }}
    </div>
@endif
