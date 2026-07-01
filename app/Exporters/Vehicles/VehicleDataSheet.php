<?php

namespace App\Exporters\Vehicles;

use App\Exporters\Support\BaseDataSheet;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

final class VehicleDataSheet extends BaseDataSheet
{
    /**
     * @return Collection<int, object>
     */
    protected function records(): Collection
    {
        return Vehicle::query()
            ->with('owner')
            ->orderBy('id')
            ->get();
    }

    public function title(): string
    {
        return (string) __('exports.vehicles.sheets.data');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 34,
            'B' => 18,
            'C' => 22,
            'D' => 22,
            'E' => 12,
            'F' => 18,
            'G' => 18,
        ];
    }

    protected function value(object $record, string $key): mixed
    {
        if (! $record instanceof Vehicle) {
            return null;
        }

        return match ($key) {
            'owner_email' => $record->owner?->email,
            'license_plate' => $record->license_plate,
            'brand' => $record->brand,
            'model' => $record->model,
            'year' => $record->year,
            'color' => $record->color,
            'odometer_km' => $record->odometer_km,
            default => null,
        };
    }
}
