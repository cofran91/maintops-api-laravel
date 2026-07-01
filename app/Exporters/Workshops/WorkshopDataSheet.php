<?php

namespace App\Exporters\Workshops;

use App\Exporters\Support\BaseDataSheet;
use App\Models\Workshop;
use Illuminate\Support\Collection;

final class WorkshopDataSheet extends BaseDataSheet
{
    /**
     * @return Collection<int, object>
     */
    protected function records(): Collection
    {
        return Workshop::query()
            ->with([
                'manager',
                'vehicleSystems' => fn ($query) => $query->orderBy('code'),
                'technicians' => fn ($query) => $query->orderBy('email'),
            ])
            ->orderBy('id')
            ->get();
    }

    public function title(): string
    {
        return (string) __('exports.workshops.sheets.data');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 34,
            'B' => 30,
            'C' => 22,
            'D' => 16,
            'E' => 42,
            'F' => 20,
            'G' => 22,
            'H' => 34,
            'I' => 34,
            'J' => 46,
            'K' => 82,
        ];
    }

    protected function value(object $record, string $key): mixed
    {
        if (! $record instanceof Workshop) {
            return null;
        }

        return match ($key) {
            'manager_email' => $record->manager?->email,
            'name' => $record->name,
            'code' => $record->code,
            'is_active' => (bool) $record->is_active,
            'address' => $record->address,
            'city' => $record->city,
            'phone' => $record->phone,
            'email' => $record->email,
            'vehicle_system_codes' => $record->vehicleSystems->pluck('code')->implode(', '),
            'technician_emails' => $record->technicians->pluck('email')->implode(', '),
            'weekly_schedule' => $this->json($record->weekly_schedule ?? []),
            default => null,
        };
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
