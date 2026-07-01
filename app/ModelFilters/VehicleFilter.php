<?php

namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class VehicleFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'license_plate',
        'brand',
        'model',
        'year',
        'color',
        'owner_id',
        'created_from',
        'created_to',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->where('license_plate', 'like', '%'.Str::upper($value).'%')
                ->orWhere('brand', 'like', '%'.$value.'%')
                ->orWhere('model', 'like', '%'.$value.'%')
                ->orWhere('color', 'like', '%'.$value.'%')
                ->orWhereHas('owner', function (Builder $query) use ($value): void {
                    $query
                        ->where('name', 'like', '%'.$value.'%')
                        ->orWhere('email', 'like', '%'.$value.'%')
                        ->orWhere('document_number', 'like', '%'.$value.'%');
                });
        });
    }

    public function licensePlate(string $value): void
    {
        $this->where('license_plate', 'like', '%'.Str::upper($value).'%');
    }

    public function brand(string $value): void
    {
        $this->where('brand', 'like', '%'.$value.'%');
    }

    public function model(string $value): void
    {
        $this->where('model', 'like', '%'.$value.'%');
    }

    public function year(int|string $value): void
    {
        $this->where('year', $value);
    }

    public function color(string $value): void
    {
        $this->where('color', 'like', '%'.$value.'%');
    }

    public function ownerId(int|string $value): void
    {
        $this->where('owner_id', $value);
    }

    public function createdFrom(string $value): void
    {
        $this->whereDateFrom('created_at', $value);
    }

    public function createdTo(string $value): void
    {
        $this->whereDateTo('created_at', $value);
    }
}
