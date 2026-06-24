<?php

namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;

final class OwnerFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'is_active',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->where('name', 'like', '%'.$value.'%')
                ->orWhere('email', 'like', '%'.$value.'%')
                ->orWhere('phone', 'like', '%'.$value.'%')
                ->orWhere('document_number', 'like', '%'.$value.'%');
        });
    }

    public function isActive(bool|int|string $value): void
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($isActive === null) {
            return;
        }

        $this->where('is_active', $isActive);
    }
}
