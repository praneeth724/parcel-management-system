<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ParcelTracking
 */
class ParcelTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The public tracking endpoint is unauthenticated, so staff identities
        // are reduced to a generic label there.
        $isPublic = $request->user() === null;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'label' => $this->status->label(),
            'location' => $this->location,
            'remarks' => $this->remarks,
            'updated_by' => $isPublic ? $this->public_actor_name : $this->actor_name,
            'happened_at' => $this->happened_at?->toIso8601String(),
        ];
    }
}
