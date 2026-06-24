<?php

namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class VehicleSystemFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'code',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->where('code', 'like', '%'.Str::upper($value).'%')
                ->orWhere('name', 'like', '%'.$value.'%');
        });
    }

    public function code(string $value): void
    {
        $this->where('code', Str::upper($value));
    }
}
