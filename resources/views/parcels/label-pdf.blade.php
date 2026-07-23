{{--
    dompdf version of the shipping label.

    dompdf cannot load the Vite bundle, so the styles are inlined here. The
    layout intentionally mirrors resources/scss/_print.scss.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Label {{ $parcel->tracking_number }}</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 6mm;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #000;
        }

        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 3mm;
            margin-bottom: 3mm;
        }

        .brand { font-size: 15px; font-weight: bold; }
        .branch { font-size: 9px; }

        .priority {
            border: 2px solid #000;
            padding: 1mm 2mm;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            float: right;
        }

        .tracking {
            text-align: center;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 2mm 0;
            margin: 3mm 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .section { margin-bottom: 3mm; }

        .section-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #000;
            margin-bottom: 1mm;
        }

        .value { font-size: 11px; line-height: 1.35; }
        .receiver-name { font-size: 13px; font-weight: bold; }

        table.meta { width: 100%; font-size: 10px; border-collapse: collapse; }
        table.meta td { padding: 0.5mm 0; width: 50%; }

        .qr { text-align: center; margin-top: 3mm; }
        .qr svg { width: 30mm; height: 30mm; }

        .footer {
            border-top: 1px solid #000;
            padding-top: 2mm;
            margin-top: 3mm;
            font-size: 8px;
            text-align: center;
        }

        .clear { clear: both; }
    </style>
</head>
<body>

<div class="header">
    <div class="priority">{{ $parcel->priority->label() }}</div>
    <div class="brand">{{ config('app.name') }}</div>
    <div class="branch">{{ $parcel->branch?->name }} &middot; {{ $parcel->branch?->contact_number }}</div>
    <div class="clear"></div>
</div>

<div class="tracking">{{ $parcel->tracking_number }}</div>

<div class="section">
    <div class="section-label">From (Sender)</div>
    <div class="value">
        <strong>{{ $parcel->customer?->full_name }}</strong>
        @if ($parcel->customer?->company_name)
            <br>{{ $parcel->customer->company_name }}
        @endif
        <br>{{ $parcel->pickup_address }}
        <br>{{ $parcel->customer?->city }}
        <br>Tel: {{ $parcel->customer?->mobile }}
    </div>
</div>

<div class="section">
    <div class="section-label">To (Receiver)</div>
    <div class="value">
        <span class="receiver-name">{{ $parcel->receiver_name }}</span>
        <br>{{ $parcel->receiver_address }}
        <br><strong>{{ $parcel->receiver_city }}</strong>
        @if ($parcel->receiver_postal_code)
            {{ $parcel->receiver_postal_code }}
        @endif
        <br>Tel: {{ $parcel->receiver_phone }}
    </div>
</div>

<div class="section">
    <div class="section-label">Shipment</div>
    <table class="meta">
        <tr>
            <td><strong>Type:</strong> {{ $parcel->parcel_type->label() }}</td>
            <td><strong>Weight:</strong> {{ rtrim(rtrim($parcel->weight, '0'), '.') }} kg</td>
        </tr>
        <tr>
            <td><strong>Dimensions:</strong> {{ $parcel->dimensions ?? '—' }}</td>
            <td><strong>Booked:</strong> {{ $parcel->created_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Payment:</strong> {{ $parcel->payment_method->label() }}</td>
            <td>
                <strong>{{ $parcel->payment_method->isCollectedOnDelivery() ? 'Collect:' : 'Charge:' }}</strong>
                Rs. {{ number_format($parcel->payment_method->isCollectedOnDelivery()
                    ? ($parcel->cod_amount ?: $parcel->delivery_charge)
                    : $parcel->delivery_charge, 2) }}
            </td>
        </tr>
    </table>
</div>

@if ($parcel->special_instructions)
    <div class="section">
        <div class="section-label">Handling instructions</div>
        <div class="value" style="font-size: 10px;">{{ $parcel->special_instructions }}</div>
    </div>
@endif

<div class="qr">
    {!! $qrSvg !!}
    <div style="font-size: 8px;">Scan to track this shipment</div>
</div>

<div class="footer">
    {{ config('app.name') }} &middot; Track at {{ route('track.index') }}
    <br>Printed {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
