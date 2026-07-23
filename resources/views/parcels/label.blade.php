{{-- Printable shipping label, sized for a 100 × 150 mm thermal label. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label {{ $parcel->tracking_number }}</title>
    @vite(['resources/scss/app.scss'])
</head>
<body class="bg-light py-4">

    <div class="text-center mb-3 no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print label
        </button>
        <a href="{{ route('parcels.label.pdf', $parcel) }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
        </a>
        <a href="{{ route('parcels.show', $parcel) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to parcel
        </a>
    </div>

    @include('parcels.partials.label-body')

</body>
</html>
