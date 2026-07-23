<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Driver
 */
class DriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_code' => $this->driver_code,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'photo_url' => $this->photo_url,

            'vehicle' => [
                'number' => $this->vehicle_number,
                'type' => $this->vehicle_type->value,
                'type_label' => $this->vehicle_type->label(),
                'capacity_kg' => $this->vehicle_type->capacityKg(),
            ],

            'license' => [
                'number' => $this->license_number,
                'expiry' => $this->license_expiry?->toDateString(),
                'expired' => $this->license_has_expired,
            ],

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'is_assignable' => $this->is_assignable,

            'branch' => new BranchResource($this->whenLoaded('branch')),

            'performance' => $this->when(
                $this->completed_deliveries_count !== null,
                fn (): array => [
                    'completed' => (int) $this->completed_deliveries_count,
                    'failed' => (int) ($this->failed_deliveries_count ?? 0),
                    'active' => (int) ($this->active_deliveries_count ?? 0),
                    'success_rate' => $this->success_rate,
                ]
            ),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
