<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'full_name' => $this->full_name,
            'company_name' => $this->company_name,
            'nic_passport' => $this->nic_passport,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'full_address' => $this->full_address,
            // A caller may eager load only a few columns (for example
            // `customer:id,full_name`), in which case the enum is absent
            // rather than wrong — so it is emitted conditionally.
            'status' => $this->when($this->status !== null, fn (): array => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ]),
            'can_book_parcels' => $this->when(
                $this->status !== null,
                fn (): bool => $this->can_book
            ),

            'branch' => new BranchResource($this->whenLoaded('branch')),
            'parcels_count' => $this->whenCounted('parcels'),
            'parcels' => ParcelResource::collection($this->whenLoaded('parcels')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
