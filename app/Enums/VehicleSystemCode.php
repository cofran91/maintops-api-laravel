<?php

namespace App\Enums;

enum VehicleSystemCode: string
{
    case Engine = 'ENGINE';
    case Brakes = 'BRAKES';
    case Electrical = 'ELECTRICAL';
    case Cooling = 'COOLING';
    case Transmission = 'TRANSMISSION';
    case Fuel = 'FUEL';
    case Hydraulic = 'HYDRAULIC';
    case Suspension = 'SUSPENSION';
    case Tires = 'TIRES';
    case Bodywork = 'BODYWORK';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $system): string => $system->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Engine => 'Engine',
            self::Brakes => 'Brakes',
            self::Electrical => 'Electrical',
            self::Cooling => 'Cooling',
            self::Transmission => 'Transmission',
            self::Fuel => 'Fuel',
            self::Hydraulic => 'Hydraulic',
            self::Suspension => 'Suspension',
            self::Tires => 'Tires',
            self::Bodywork => 'Bodywork',
        };
    }
}
