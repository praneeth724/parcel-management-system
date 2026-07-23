<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ParcelTrackingResource;
use App\Models\Parcel;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated parcel tracking.
 *
 * The payload is deliberately reduced: no full addresses, no phone numbers and
 * no staff names, because anyone holding a tracking number can call this.
 */
class TrackingController extends ApiController
{
    public function __construct(private readonly QrCodeService $qrCode) {}

    public function show(string $trackingNumber): JsonResponse
    {
        $parcel = Parcel::query()
            ->where('tracking_number', strtoupper(trim($trackingNumber)))
            ->with(['trackings', 'customer:id,full_name,company_name,city', 'branch:id,name,city'])
            ->first();

        if ($parcel === null) {
            return $this->error(
                "No shipment was found with tracking number {$trackingNumber}.",
                \Illuminate\Http\Response::HTTP_NOT_FOUND
            );
        }

        return $this->success([
            'tracking_number' => $parcel->tracking_number,

            'status' => [
                'value' => $parcel->status->value,
                'label' => $parcel->status->label(),
            ],

            'priority' => $parcel->priority->label(),
            'is_overdue' => $parcel->is_overdue,

            // Names are masked; full addresses are withheld entirely.
            'sender' => [
                'name' => $parcel->customer?->company_name
                    ?: \Illuminate\Support\Str::mask($parcel->customer?->full_name ?? '', '*', 3),
                'city' => $parcel->customer?->city,
            ],

            'receiver' => [
                'name' => \Illuminate\Support\Str::mask($parcel->receiver_name, '*', 3),
                'city' => $parcel->receiver_city,
            ],

            'shipment' => [
                'type' => $parcel->parcel_type->label(),
                'weight_kg' => (float) $parcel->weight,
                'dimensions' => $parcel->dimensions,
                'payment_method' => $parcel->payment_method->label(),
                'payment_status' => $parcel->payment_status->label(),
                'handled_by' => $parcel->branch?->name,
                'delivery_attempts' => $parcel->delivery_attempts,
            ],

            'dates' => [
                'booked_at' => $parcel->created_at?->toIso8601String(),
                'expected_delivery_at' => $parcel->expected_delivery_at?->toIso8601String(),
                'delivered_at' => $parcel->delivered_at?->toIso8601String(),
            ],

            'timeline' => ParcelTrackingResource::collection(
                $parcel->trackings->sortByDesc('happened_at')->values()
            ),

            'qr_code' => $this->qrCode->dataUri($parcel, 200),
        ]);
    }
}
