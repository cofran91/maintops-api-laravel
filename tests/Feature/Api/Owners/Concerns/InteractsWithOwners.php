<?php

namespace Tests\Feature\Api\Owners\Concerns;

use App\Models\Owner;

trait InteractsWithOwners
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function ownerPayload(int $index = 1, array $attributes = []): array
    {
        return array_merge([
            'name' => 'Owner '.$index,
            'email' => 'OWNER.'.$index.'@EXAMPLE.COM',
            'is_active' => true,
            'phone' => '+57 300 555 00'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'document_number' => 'OWNER-DOC-'.$index,
            'address' => 'Street '.$index.' #10-20',
        ], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function owner(array $attributes = []): Owner
    {
        return Owner::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function ownerUpdatePayloadFor(Owner $owner, array $attributes = []): array
    {
        return array_merge([
            'name' => $owner->name,
            'email' => $owner->email,
            'is_active' => (bool) $owner->is_active,
            'phone' => $owner->phone,
            'document_number' => $owner->document_number,
            'address' => $owner->address,
        ], $attributes);
    }
}
