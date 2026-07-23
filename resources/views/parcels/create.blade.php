@extends('layouts.app')

@section('title', 'Book a parcel')

@section('content')
    <x-page-header title="Book a parcel"
                   subtitle="The tracking number and QR code are generated automatically once you save."
                   :back="route('parcels.index')" />

    <form method="POST" action="{{ route('parcels.store') }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Sender --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person text-primary"></i> Sender</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-{{ $branches->count() > 1 ? '7' : '12' }}">
                                <x-form.field name="customer_id"
                                              label="Customer"
                                              :value="$selectedCustomer"
                                              :options="$customers"
                                              placeholder="— Select a customer —"
                                              :required="true"
                                              help="Only Active customers can book shipments." />
                            </div>

                            @if ($branches->count() > 1)
                                <div class="col-md-5">
                                    <x-form.field name="branch_id"
                                                  label="Booking branch"
                                                  :options="$branches"
                                                  placeholder="— Select a branch —"
                                                  :required="true" />
                                </div>
                            @else
                                <input type="hidden" name="branch_id" value="{{ $branches->keys()->first() }}">
                            @endif

                            <div class="col-12">
                                <x-form.field name="pickup_address"
                                              type="textarea"
                                              label="Pickup address"
                                              :rows="2"
                                              placeholder="Where should the driver collect this parcel?"
                                              :required="true" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Receiver --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt text-danger"></i> Receiver</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <x-form.field name="receiver_name"
                                              label="Receiver name"
                                              placeholder="Kamala Silva"
                                              :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="receiver_phone"
                                              label="Receiver phone"
                                              placeholder="0771234567"
                                              help="Sri Lankan mobile number"
                                              :required="true" />
                            </div>
                            <div class="col-12">
                                <x-form.field name="receiver_address"
                                              type="textarea"
                                              label="Delivery address"
                                              :rows="2"
                                              :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="receiver_city"
                                              id="receiver_city"
                                              label="City"
                                              placeholder="Kandy"
                                              :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="receiver_postal_code"
                                              label="Postal code"
                                              placeholder="20000" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Parcel --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam text-warning"></i> Parcel</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <x-form.field name="parcel_type"
                                              id="parcel_type"
                                              label="Parcel type"
                                              :options="$parcelTypes"
                                              value="package"
                                              :required="true" />
                            </div>
                            <div class="col-md-4">
                                <x-form.field name="weight"
                                              id="weight"
                                              type="number"
                                              label="Weight (kg)"
                                              step="0.001"
                                              min="0.001"
                                              placeholder="1.5"
                                              :required="true" />
                            </div>
                            <div class="col-md-4">
                                <x-form.field name="priority"
                                              id="priority"
                                              label="Delivery priority"
                                              :options="$priorities"
                                              value="normal"
                                              :required="true" />
                            </div>

                            <div class="col-12">
                                <label class="form-label">Dimensions (cm)</label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <x-form.field name="length_cm" id="length_cm" type="number" step="0.1" min="0.1" placeholder="Length" />
                                    </div>
                                    <div class="col-4">
                                        <x-form.field name="width_cm" id="width_cm" type="number" step="0.1" min="0.1" placeholder="Width" />
                                    </div>
                                    <div class="col-4">
                                        <x-form.field name="height_cm" id="height_cm" type="number" step="0.1" min="0.1" placeholder="Height" />
                                    </div>
                                </div>
                                <div class="form-text mt-0 mb-3">
                                    Optional, but all three are needed together. Large light parcels are
                                    billed on volumetric weight (L × W × H ÷ 5000).
                                </div>
                            </div>

                            <div class="col-12">
                                <x-form.field name="special_instructions"
                                              type="textarea"
                                              label="Special instructions"
                                              :rows="2"
                                              placeholder="Optional — anything the driver should know" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Photos --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-camera text-info"></i> Parcel photos</h6>
                    </div>
                    <div class="card-body">
                        <input type="file"
                               name="images[]"
                               multiple
                               accept="image/*"
                               class="form-control @error('images.*') is-invalid @enderror">
                        @error('images.*')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @else
                            <div class="form-text">
                                Up to {{ config('courier.uploads.max_parcel_images') }} images,
                                {{ round(config('courier.uploads.max_image_kb') / 1024) }} MB each.
                                Useful evidence of the parcel's condition at booking.
                            </div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Charges sidebar --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Charges</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="quoteButton">
                            <i class="bi bi-calculator"></i> Calculate
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="quoteBreakdown" class="small text-muted mb-3 d-none">
                            <div class="d-flex justify-content-between"><span>Base charge</span><span id="qBase">—</span></div>
                            <div class="d-flex justify-content-between"><span>Weight surcharge</span><span id="qWeight">—</span></div>
                            <div class="d-flex justify-content-between"><span>Handling fee</span><span id="qHandling">—</span></div>
                            <div class="d-flex justify-content-between"><span>Priority multiplier</span><span id="qMultiplier">—</span></div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between fw-bold text-dark">
                                <span>Suggested total</span><span id="qTotal">—</span>
                            </div>
                        </div>

                        <x-form.field name="delivery_charge"
                                      id="delivery_charge"
                                      type="number"
                                      label="Delivery charge"
                                      step="0.01"
                                      min="0"
                                      :prefix="$pricing['currency_symbol']"
                                      :required="true"
                                      help="Click Calculate for a suggestion, then adjust if needed." />

                        <x-form.field name="payment_method"
                                      id="payment_method"
                                      label="Payment method"
                                      :options="$paymentMethods"
                                      value="cash_on_delivery"
                                      :required="true" />

                        <x-form.field name="cod_amount"
                                      id="cod_amount"
                                      type="number"
                                      label="Cash to collect"
                                      step="0.01"
                                      min="0"
                                      value="0"
                                      :prefix="$pricing['currency_symbol']"
                                      help="Goods value the driver collects at the door. Cash on delivery only." />
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-check-lg me-1"></i> Book parcel
                    </button>
                    <a href="{{ route('parcels.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('quoteButton');
    const breakdown = document.getElementById('quoteBreakdown');

    const fmt = (n) => 'Rs. ' + Number(n).toLocaleString('en-LK', { minimumFractionDigits: 2 });

    button?.addEventListener('click', async () => {
        const weight = document.getElementById('weight').value;

        if (!weight || Number(weight) <= 0) {
            alert('Enter the parcel weight first.');
            return;
        }

        const params = new URLSearchParams({
            weight,
            priority: document.getElementById('priority').value,
            parcel_type: document.getElementById('parcel_type').value,
            destination_city: document.getElementById('receiver_city').value || '',
        });

        for (const dim of ['length_cm', 'width_cm', 'height_cm']) {
            const value = document.getElementById(dim).value;
            if (value) params.append(dim, value);
        }

        button.disabled = true;

        try {
            const response = await fetch(`{{ route('parcels.quote') }}?${params}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('Quote failed');

            const { data } = await response.json();

            document.getElementById('qBase').textContent = fmt(data.base);
            document.getElementById('qWeight').textContent = fmt(data.weight_surcharge);
            document.getElementById('qHandling').textContent = fmt(data.handling_fee);
            document.getElementById('qMultiplier').textContent = '× ' + data.priority_multiplier;
            document.getElementById('qTotal').textContent = data.formatted_total;
            document.getElementById('delivery_charge').value = data.total;

            breakdown.classList.remove('d-none');
        } catch (e) {
            alert('Could not calculate a quote. Please check the weight and try again.');
        } finally {
            button.disabled = false;
        }
    });

    // Cash to collect only applies to cash on delivery.
    const method = document.getElementById('payment_method');
    const cod = document.getElementById('cod_amount');

    const syncCod = () => {
        const isCod = method.value === 'cash_on_delivery';
        cod.disabled = !isCod;
        if (!isCod) cod.value = 0;
    };

    method?.addEventListener('change', syncCod);
    syncCod();
});
</script>
@endpush
