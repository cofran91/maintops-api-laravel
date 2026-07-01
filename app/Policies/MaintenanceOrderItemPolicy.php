<?php

namespace App\Policies;

use App\Models\MaintenanceOrderItem;
use App\Models\User;
use App\Support\MaintenanceOrders\MaintenanceOrderAccess;

final class MaintenanceOrderItemPolicy
{
    public function viewAny(User $user): bool
    {
        return MaintenanceOrderAccess::canViewAnyOrder($user);
    }

    public function view(User $user, MaintenanceOrderItem $item): bool
    {
        return MaintenanceOrderAccess::canViewOrder($user, $item->maintenanceOrder);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MaintenanceOrderItem $item): bool
    {
        return MaintenanceOrderAccess::canUpdateOrderItem($user, $item->maintenanceOrder);
    }
}
