<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\Users\UserAuditSnapshotService;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateUserAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly UserAuditSnapshotService $snapshotService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            /** @var User $user */
            $user = User::withoutAuditing(function () use ($attributes): User {
                $user = User::query()->create(Arr::except($attributes, ['role', 'password_confirmation']));
                $user->syncRoles([$attributes['role']]);

                return $user->refresh();
            });

            $this->auditRecorder->record(
                $user,
                'user created',
                newValues: $this->snapshotService->snapshot($user),
            );

            return $user;
        });
    }
}
