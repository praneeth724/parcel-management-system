<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Parcel
 */
class ParcelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,

            'receiver' => [
                'name' => $this->receiver_name,
                'phone' => $this->receiver_phone,
                'address' => $this->receiver_address,
                'city' => $this->receiver_city,
                'postal_code' => $this->receiver_postal_code,
                'full_address' => $this->receiver_full_address,
            ],

            'pickup_address' => $this->pickup_address,

            'parcel' => [
                'type' => $this->parcel_type->value,
                'type_label' => $this->parcel_type->label(),
                'weight_kg' => (float) $this->weight,
                'length_cm' => $this->length_cm ? (float) $this->length_cm : null,
                'width_cm' => $this->width_cm ? (float) $this->width_cm : null,
                'height_cm' => $this->height_cm ? (float) $this->height_cm : null,
                'dimensions' => $this->dimensions,
                'volumetric_weight_kg' => $this->volumetric_weight,
                'chargeable_weight_kg' => $this->chargeable_weight,
            ],

            'charges' => [
                'currency' => config('courier.pricing.currency'),
                'delivery_charge' => (float) $this->delivery_charge,
                'cod_amount' => (float) $this->cod_amount,
                'payment_method' => $this->payment_method->value,
                'payment_method_label' => $this->payment_method->label(),
                'payment_status' => $this->payment_status->value,
                'payment_status_label' => $this->payment_status->label(),
            ],

            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
            ],

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_final' => $this->status->isFinal(),
                'allowed_transitions' => array_map(
                    fn ($s) => $s->value,
                    $this->status->allowedTransitions()
                ),
            ],

            'flags' => [
                'is_overdue' => $this->is_overdue,
                'is_editable' => $this->is_editable,
                'can_be_cancelled' => $this->can_be_cancelled,
            ],

            'delivery_attempts' => $this->delivery_attempts,
            'special_instructions' => $this->special_instructions,

            'dates' => [
                'booked_at' => $this->created_at?->toIso8601String(),
                'expected_delivery_at' => $this->expected_delivery_at?->toIso8601String(),
                'picked_up_at' => $this->picked_up_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            ],

            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'trackings' => ParcelTrackingResource::collection($this->whenLoaded('trackings')),
            'deliveries' => DeliveryResource::collection($this->whenLoaded('deliveries')),
            'active_delivery' => new DeliveryResource($this->whenLoaded('activeDelivery')),

            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'caption' => $image->caption,
                'size' => $image->size,
            ])),

            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
        ];
    }
}
