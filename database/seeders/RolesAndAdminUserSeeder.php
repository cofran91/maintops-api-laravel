<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guardName = config('auth.defaults.guard') ?: 'web';

        Role::query()
            ->where('guard_name', $guardName)
            ->where('name', 'workshop_admin')
            ->delete();

        foreach (SystemRole::cases() as $role) {
            Role::findOrCreate($role->value, $guardName);
        }

        $admin = User::withTrashed()->firstOrNew([
            'email' => 'admin@maint.test',
        ]);

        $admin->forceFill([
            'name' => 'Maint Admin',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();

        if ($admin->trashed()) {
            $admin->restore();
        }

        $admin->syncRoles([SystemRole::SuperAdmin->value]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
