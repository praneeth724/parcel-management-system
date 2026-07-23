<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Delivery
 */
class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attempt_number' => $this->attempt_number,

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_open' => $this->status->isOpen(),
            ],

            'actions' => [
                'can_respond' => $this->can_be_responded_to,
                'can_complete' => $this->can_be_completed,
            ],

            'timestamps' => [
                'assigned_at' => $this->assigned_at?->toIso8601String(),
                'accepted_at' => $this->accepted_at?->toIso8601String(),
                'rejected_at' => $this->rejected_at?->toIso8601String(),
                'picked_up_at' => $this->picked_up_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'failed_at' => $this->failed_at?->toIso8601String(),
            ],

            'duration_minutes' => $this->duration_minutes,

            'proof_of_delivery' => [
                'received_by' => $this->received_by,
                'receiver_nic' => $this->receiver_nic,
                'location' => $this->delivery_location,
                'latitude' => $this->delivery_latitude ? (float) $this->delivery_latitude : null,
                'longitude' => $this->delivery_longitude ? (float) $this->delivery_longitude : null,
                'signature_url' => $this->signature_url,
                'proof_image_url' => $this->proof_image_url,
                'cod_collected' => $this->cod_collected ? (float) $this->cod_collected : null,
            ],

            'rejection_reason' => $this->rejection_reason,
            'failure_reason' => $this->failure_reason,
            'notes' => $this->notes,

            'driver' => new DriverResource($this->whenLoaded('driver')),
            'parcel' => new ParcelResource($this->whenLoaded('parcel')),

            'assigned_by' => $this->whenLoaded('assignedBy', fn () => [
                'id' => $this->assignedBy?->id,
                'name' => $this->assignedBy?->name,
            ]),
        ];
    }
}
