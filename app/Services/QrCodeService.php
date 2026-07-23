<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Parcel;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

/**
 * Generates the per-parcel QR code (bonus requirement).
 *
 * The code encodes the public tracking URL rather than the parcel data itself,
 * so a scan always shows the *current* status — sender, receiver and status are
 * rendered live on the tracking page instead of being frozen into the image.
 *
 * SVG output is used deliberately: it needs no imagick extension, stays crisp
 * on a printed label at any size, and is a fraction of the size of a PNG.
 */
class QrCodeService
{
    private const DEFAULT_SIZE = 300;

    /**
     * Render the QR code as raw SVG markup for inline embedding in Blade.
     */
    public function svg(Parcel $parcel, int $size = self::DEFAULT_SIZE): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($parcel->tracking_url);
    }

    /**
     * Render as a data URI, which survives being passed into dompdf.
     */
    public function dataUri(Parcel $parcel, int $size = self::DEFAULT_SIZE): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($parcel, $size));
    }

    /**
     * Write the QR code to disk and record the path on the parcel.
     *
     * Failure here is logged but never fatal: a parcel without a stored QR file
     * still renders one on demand, so booking must not fail over it.
     */
    public function generateAndStore(Parcel $parcel, int $size = self::DEFAULT_SIZE): ?string
    {
        try {
            $path = sprintf(
                '%s/%s.svg',
                config('courier.uploads.paths.qr_codes'),
                $parcel->tracking_number
            );

            Storage::disk('public')->put($path, $this->svg($parcel, $size));

            $parcel->forceFill(['qr_path' => $path])->saveQuietly();

            return $path;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Public URL of the stored QR file, if one exists.
     */
    public function storedUrl(Parcel $parcel): ?string
    {
        if (blank($parcel->qr_path) || ! Storage::disk('public')->exists($parcel->qr_path)) {
            return null;
        }

        return Storage::disk('public')->url($parcel->qr_path);
    }

    /**
     * Everything a scan should reveal, per the specification.
     *
     * @return array<string, mixed>
     */
    public function payload(Parcel $parcel): array
    {
        return [
            'tracking_number' => $parcel->tracking_number,
            'tracking_url' => $parcel->tracking_url,
            'sender' => [
                'name' => $parcel->customer?->full_name,
                'company' => $parcel->customer?->company_name,
                'mobile' => $parcel->customer?->mobile,
                'city' => $parcel->customer?->city,
            ],
            'receiver' => [
                'name' => $parcel->receiver_name,
                'phone' => $parcel->receiver_phone,
                'address' => $parcel->receiver_full_address,
            ],
            'status' => $parcel->status->label(),
            'priority' => $parcel->priority->label(),
        ];
    }
}
