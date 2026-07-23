{{-- Expects: $recentParcels --}}

<div class="table-card">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-3">
        <h6 class="mb-0 fw-bold">Recent shipments</h6>
        <a href="{{ route('parcels.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
    </div>

    @if ($recentParcels->isEmpty())
        <x-empty-state icon="bi-box-seam"
                       title="No shipments yet"
                       message="Booked parcels will appear here." />
    @else
        <div class="table-card__scroll">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Tracking No.</th>
                        <th>Customer</th>
                        <th>Destination</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Booked</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentParcels as $parcel)
                        <tr>
                            <td>
                                <a href="{{ route('parcels.show', $parcel) }}" class="tracking-code text-decoration-none">
                                    {{ $parcel->tracking_number }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $parcel->customer?->full_name ?? '—' }}</div>
                                <small class="text-muted">{{ $parcel->customer?->customer_code }}</small>
                            </td>
                            <td>{{ $parcel->receiver_city }}</td>
                            <td><x-status-badge :status="$parcel->priority" /></td>
                            <td><x-status-badge :status="$parcel->status" /></td>
                            <td>
                                <span data-bs-toggle="tooltip" title="{{ $parcel->created_at->format('d M Y, H:i') }}">
                                    {{ $parcel->created_at->diffForHumans() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
