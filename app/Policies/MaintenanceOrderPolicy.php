<?php

namespace App\Policies;

use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\MaintenanceOrders\MaintenanceOrderAccess;

final class MaintenanceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return MaintenanceOrderAccess::canViewAnyOrder($user);
    }

    public function view(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return MaintenanceOrderAccess::canViewOrder($user, $maintenanceOrder);
    }

    public function create(User $user): bool
    {
        return MaintenanceOrderAccess::canCreateOrder($user);
    }

    public function update(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return MaintenanceOrderAccess::canUpdateOrder($user, $maintenanceOrder);
    }
}
