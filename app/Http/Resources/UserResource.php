<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => [
                'value' => $this->role->value,
                'label' => $this->role->label(),
            ],
            'is_active' => $this->is_active,
            'email_verified' => $this->hasVerifiedEmail(),
            'avatar_url' => $this->avatar_url,

            // `whenLoaded` keeps the payload honest: a relation that was not
            // eager loaded is omitted rather than silently triggering a query.
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'driver' => new DriverResource($this->whenLoaded('driver')),

            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
