@extends('layouts.public')

@section('title', 'Track your parcel')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="text-center mb-4">
                <i class="bi bi-geo-alt-fill display-4 text-primary"></i>
                <h1 class="h3 fw-bold mt-2">Track your parcel</h1>
                <p class="text-muted">
                    Enter the tracking number from your receipt to see exactly where your shipment is.
                </p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="{{ route('track.lookup') }}" method="GET" novalidate>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-upc-scan text-muted"></i>
                            </span>
                            <input type="text"
                                   name="tracking_number"
                                   value="{{ old('tracking_number') }}"
                                   class="form-control text-uppercase @error('tracking_number') is-invalid @enderror"
                                   placeholder="SWT-20260722-A1B2C3"
                                   autocomplete="off"
                                   autofocus
                                   required>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-search me-1"></i> Track
                            </button>
                            @error('tracking_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </form>

                    <p class="text-muted small mb-0 mt-3">
                        <i class="bi bi-info-circle"></i>
                        Your tracking number looks like <code>SWT-20260722-A1B2C3</code> and appears
                        on your booking receipt and on the shipping label.
                    </p>
                </div>
            </div>

            {{-- What the statuses mean, so a customer is not left guessing. --}}
            <div class="card border-0 bg-white shadow-sm mt-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">What the statuses mean</h6>

                    <div class="row g-3">
                        @foreach (App\Enums\ParcelStatus::cases() as $status)
                            @continue($status === App\Enums\ParcelStatus::Cancelled)
                            <div class="col-sm-6">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi {{ $status->icon() }} text-{{ $status->color() }} mt-1"></i>
                                    <div>
                                        <div class="fw-semibold small">{{ $status->label() }}</div>
                                        <div class="text-muted" style="font-size: 0.8rem;">
                                            @switch($status)
                                                @case(App\Enums\ParcelStatus::Pending)
                                                    Booked and waiting to be collected.
                                                    @break
                                                @case(App\Enums\ParcelStatus::PickedUp)
                                                    Collected from the sender.
                                                    @break
                                                @case(App\Enums\ParcelStatus::AtWarehouse)
                                                    Received and being sorted at our facility.
                                                    @break
                                                @case(App\Enums\ParcelStatus::OutForDelivery)
                                                    On the vehicle, heading to the receiver.
                                                    @break
                                                @case(App\Enums\ParcelStatus::Delivered)
                                                    Handed over to the receiver.
                                                    @break
                                                @case(App\Enums\ParcelStatus::FailedDelivery)
                                                    We could not deliver; we will try again.
                                                    @break
                                                @case(App\Enums\ParcelStatus::Returned)
                                                    Sent back to the sender.
                                                    @break
                                            @endswitch
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
