<?php

namespace App\Enums;

enum SystemRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case WorkshopManager = 'workshop_manager';
    case Advisor = 'advisor';
    case Technician = 'technician';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
