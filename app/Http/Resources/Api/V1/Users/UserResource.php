<?php

namespace App\Http\Resources\Api\V1\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'preferred_locale' => $this->preferred_locale,
            'roles' => $this->getRoleNames()->values()->all(),
            'is_active' => (bool) $this->is_active,
            'phone' => $this->phone,
            'document_number' => $this->document_number,
            'address' => $this->address,
            'workshop_id' => $this->workshop_id,
            'workshop' => $this->whenLoaded('workshop', function (): ?array {
                if ($this->workshop === null) {
                    return null;
                }

                return [
                    'id' => $this->workshop->id,
                    'name' => $this->workshop->name,
                    'code' => $this->workshop->code,
                    'city' => $this->workshop->city,
                    'is_active' => (bool) $this->workshop->is_active,
                ];
            }),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
