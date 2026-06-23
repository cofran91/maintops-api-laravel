<?php

namespace Tests\Feature\Api\Users\Concerns;

use App\Enums\SystemRole;
use App\Models\User;

trait InteractsWithUsers
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(SystemRole $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => $role->value.'.'.uniqid('', true).'@example.com',
        ], $attributes));

        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(SystemRole $role, int $index = 1, array $attributes = []): array
    {
        return array_merge([
            'name' => 'User '.$role->value,
            'email' => $role->value.'.'.$index.'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => $role->value,
            'is_active' => true,
            'phone' => '+57 300 123 45'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'document_number' => 'DOC-'.$role->value.'-'.$index,
            'address' => 'Street '.$index.' #10-20',
        ], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function updatePayloadFor(User $user, SystemRole $role, array $attributes = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => $role->value,
            'is_active' => (bool) $user->is_active,
            'phone' => $user->phone,
            'document_number' => $user->document_number,
            'address' => $user->address,
        ], $attributes);
    }
}
