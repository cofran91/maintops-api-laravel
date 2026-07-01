<?php

namespace App\Support\MaintenanceOrders;

use App\Enums\SystemRole;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class MaintenanceOrderAccess
{
    /**
     * @param  Builder<MaintenanceOrder>  $query
     */
    public static function scopeOrdersForActor(Builder $query, mixed $actor): void
    {
        if (! $actor instanceof User) {
            self::denyAll($query);

            return;
        }

        if (self::canManageAnyOrder($actor) || self::isPrimaryAdvisor($actor)) {
            return;
        }

        if (self::isWorkshopManager($actor)) {
            $query->whereHas('workshop', function (Builder $query) use ($actor): void {
                $query->where('manager_user_id', $actor->getKey());
            });

            return;
        }

        if (self::isAssignedTechnicianRole($actor)) {
            $query->where('technician_id', $actor->getKey());

            return;
        }

        self::denyAll($query);
    }

    /**
     * @param  Builder<MaintenanceOrderItem>  $query
     */
    public static function scopeItemsForActor(Builder $query, mixed $actor): void
    {
        if (! $actor instanceof User) {
            self::denyAll($query);

            return;
        }

        if (self::canManageAnyOrder($actor) || self::isPrimaryAdvisor($actor)) {
            return;
        }

        if (self::isWorkshopManager($actor)) {
            $query->whereHas('maintenanceOrder.workshop', function (Builder $query) use ($actor): void {
                $query->where('manager_user_id', $actor->getKey());
            });

            return;
        }

        if (self::isAssignedTechnicianRole($actor)) {
            $query->whereHas('maintenanceOrder', function (Builder $query) use ($actor): void {
                $query->where('technician_id', $actor->getKey());
            });

            return;
        }

        self::denyAll($query);
    }

    public static function canViewAnyOrder(User $user): bool
    {
        return self::hasActiveRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
            SystemRole::WorkshopManager,
            SystemRole::Advisor,
            SystemRole::Technician,
        ]);
    }

    public static function canViewOrder(User $user, ?MaintenanceOrder $maintenanceOrder): bool
    {
        if (! $maintenanceOrder instanceof MaintenanceOrder) {
            return false;
        }

        return self::canManageAnyOrder($user)
            || self::isPrimaryAdvisor($user)
            || self::isAssignedWorkshopManager($user, $maintenanceOrder)
            || self::isAssignedTechnician($user, $maintenanceOrder);
    }

    public static function canCreateOrder(User $user): bool
    {
        return self::canManageAnyOrder($user)
            || self::isPrimaryAdvisor($user);
    }

    public static function canUpdateOrder(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return self::canManageAnyOrder($user)
            || self::isPrimaryAdvisor($user)
            || self::isAssignedWorkshopManager($user, $maintenanceOrder);
    }

    public static function canUpdateOrderItem(User $user, ?MaintenanceOrder $maintenanceOrder): bool
    {
        return self::canViewOrder($user, $maintenanceOrder);
    }

    public static function canManageAnyOrder(User $user): bool
    {
        return $user->is_active && self::isSystemAdmin($user);
    }

    public static function isPrimaryAdvisor(User $user): bool
    {
        return self::hasActiveRole($user, [SystemRole::Advisor])
            && ! self::hasAnyRole($user, [
                SystemRole::SuperAdmin,
                SystemRole::Admin,
                SystemRole::WorkshopManager,
            ]);
    }

    public static function isAssignedWorkshopManager(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        if (! self::isWorkshopManager($user)) {
            return false;
        }

        return $maintenanceOrder->workshop()
            ->where('manager_user_id', $user->getKey())
            ->exists();
    }

    public static function isAssignedTechnician(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        if (! self::isAssignedTechnicianRole($user)) {
            return false;
        }

        return (int) $maintenanceOrder->technician_id === (int) $user->getKey();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private static function denyAll(Builder $query): void
    {
        $query->whereRaw('1 = 0');
    }

    private static function isSystemAdmin(User $user): bool
    {
        return self::hasAnyRole($user, [
            SystemRole::SuperAdmin,
            SystemRole::Admin,
        ]);
    }

    private static function isWorkshopManager(User $user): bool
    {
        return self::hasActiveRole($user, [SystemRole::WorkshopManager])
            && ! self::isSystemAdmin($user);
    }

    private static function isAssignedTechnicianRole(User $user): bool
    {
        return self::hasActiveRole($user, [SystemRole::Technician])
            && ! self::hasAnyRole($user, [
                SystemRole::SuperAdmin,
                SystemRole::Admin,
                SystemRole::WorkshopManager,
                SystemRole::Advisor,
            ]);
    }

    /**
     * @param  array<int, SystemRole>  $roles
     */
    private static function hasActiveRole(User $user, array $roles): bool
    {
        return $user->is_active && self::hasAnyRole($user, $roles);
    }

    /**
     * @param  array<int, SystemRole>  $roles
     */
    private static function hasAnyRole(User $user, array $roles): bool
    {
        return $user->hasRole(array_map(
            static fn (SystemRole $role): string => $role->value,
            $roles,
        ));
    }
}
