<?php

namespace App\Exporters\Owners;

use App\Exporters\Support\BaseDataSheet;
use App\Models\Owner;
use Illuminate\Support\Collection;

final class OwnerDataSheet extends BaseDataSheet
{
    /**
     * @return Collection<int, object>
     */
    protected function records(): Collection
    {
        return Owner::query()
            ->orderBy('id')
            ->get();
    }

    public function title(): string
    {
        return (string) __('exports.owners.sheets.data');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 34,
            'C' => 16,
            'D' => 22,
            'E' => 24,
            'F' => 46,
        ];
    }

    protected function value(object $record, string $key): mixed
    {
        if (! $record instanceof Owner) {
            return null;
        }

        return match ($key) {
            'name' => $record->name,
            'email' => $record->email,
            'is_active' => (bool) $record->is_active,
            'phone' => $record->phone,
            'document_number' => $record->document_number,
            'address' => $record->address,
            default => null,
        };
    }
}
