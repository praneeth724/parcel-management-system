{{--
    The label itself, shared by the printable HTML view and the dompdf version
    so the two can never drift apart.
--}}

<div class="shipping-label">
    <div class="shipping-label__header">
        <div>
            <div class="shipping-label__brand">{{ config('app.name') }}</div>
            <div style="font-size: 10px;">{{ $parcel->branch?->name }} &middot; {{ $parcel->branch?->contact_number }}</div>
        </div>
        <div class="shipping-label__priority">{{ $parcel->priority->label() }}</div>
    </div>

    <div class="shipping-label__tracking">{{ $parcel->tracking_number }}</div>

    <div class="shipping-label__section">
        <div class="shipping-label__label">From (Sender)</div>
        <div class="shipping-label__value">
            <strong>{{ $parcel->customer?->full_name }}</strong>
            @if ($parcel->customer?->company_name)
                <br>{{ $parcel->customer->company_name }}
            @endif
            <br>{{ $parcel->pickup_address }}
            <br>{{ $parcel->customer?->city }}
            <br>Tel: {{ $parcel->customer?->mobile }}
        </div>
    </div>

    <div class="shipping-label__section">
        <div class="shipping-label__label">To (Receiver)</div>
        <div class="shipping-label__value">
            <strong style="font-size: 14px;">{{ $parcel->receiver_name }}</strong>
            <br>{{ $parcel->receiver_address }}
            <br><strong>{{ $parcel->receiver_city }}</strong>
            @if ($parcel->receiver_postal_code)
                {{ $parcel->receiver_postal_code }}
            @endif
            <br>Tel: {{ $parcel->receiver_phone }}
        </div>
    </div>

    <div class="shipping-label__section">
        <div class="shipping-label__label">Shipment</div>
        <div class="shipping-label__grid">
            <div><strong>Type:</strong> {{ $parcel->parcel_type->label() }}</div>
            <div><strong>Weight:</strong> {{ rtrim(rtrim($parcel->weight, '0'), '.') }} kg</div>
            <div><strong>Dimensions:</strong> {{ $parcel->dimensions ?? '—' }}</div>
            <div><strong>Booked:</strong> {{ $parcel->created_at->format('d/m/Y') }}</div>
            <div><strong>Payment:</strong> {{ $parcel->payment_method->label() }}</div>
            <div>
                <strong>{{ $parcel->payment_method->isCollectedOnDelivery() ? 'Collect:' : 'Charge:' }}</strong>
                Rs. {{ number_format($parcel->payment_method->isCollectedOnDelivery()
                    ? ($parcel->cod_amount ?: $parcel->delivery_charge)
                    : $parcel->delivery_charge, 2) }}
            </div>
        </div>
    </div>

    @if ($parcel->special_instructions)
        <div class="shipping-label__section">
            <div class="shipping-label__label">Handling instructions</div>
            <div class="shipping-label__value" style="font-size: 11px;">
                {{ $parcel->special_instructions }}
            </div>
        </div>
    @endif

    <div class="shipping-label__qr">
        {!! $qrSvg !!}
        <div style="font-size: 9px; margin-top: 1mm;">Scan to track this shipment</div>
    </div>

    <div class="shipping-label__footer">
        {{ config('app.name') }} &middot; Track at {{ route('track.index') }}
        <br>Printed {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
