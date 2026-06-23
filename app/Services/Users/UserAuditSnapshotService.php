<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Arr;

final class UserAuditSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        return [
            'attributes' => Arr::except($user->getAttributes(), ['password', 'remember_token']),
            'roles' => $user->roles()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
