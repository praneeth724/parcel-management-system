<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'full_address' => $this->full_address,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'is_active' => $this->is_active,

            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager?->id,
                'name' => $this->manager?->name,
                'email' => $this->manager?->email,
            ]),

            // Present only when the caller asked for counts via withCount().
            'counts' => $this->when(
                $this->drivers_count !== null || $this->parcels_count !== null,
                fn (): array => array_filter([
                    'drivers' => $this->drivers_count,
                    'staff' => $this->staff_count,
                    'customers' => $this->customers_count,
                    'parcels' => $this->parcels_count,
                ], fn ($v) => $v !== null)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
