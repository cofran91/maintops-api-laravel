<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\Users\UserAuditSnapshotService;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateUserAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly UserAuditSnapshotService $snapshotService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $oldValues = $this->snapshotService->snapshot($user);

            /** @var User $updatedUser */
            $updatedUser = User::withoutAuditing(function () use ($user, $attributes): User {
                $user->update(Arr::except($attributes, ['role', 'password_confirmation']));

                $user->syncRoles([$attributes['role']]);

                return $user->refresh();
            });

            $this->auditRecorder->record(
                $updatedUser,
                'user updated',
                oldValues: $oldValues,
                newValues: $this->snapshotService->snapshot($updatedUser),
            );

            return $updatedUser;
        });
    }
}
