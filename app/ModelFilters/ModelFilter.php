<?php

namespace App\ModelFilters;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use EloquentFilter\ModelFilter as BaseModelFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ModelFilter extends BaseModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [];

    protected $drop_id = false;

    protected function includeFilterInput($key, $value): bool
    {
        return in_array($key, $this->allowedFilters, true)
            && parent::includeFilterInput($key, $value);
    }

    public static function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 15), 1), 100);
    }

    protected function dateFilterValue(string $value): ?CarbonImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false) {
            return null;
        }

        if (
            is_array($errors)
            && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
        ) {
            return null;
        }

        return CarbonImmutable::instance($date);
    }

    protected function booleanFilterValue(bool|int|string $value): ?bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    protected function positiveInteger(int|string $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : $integer;
    }

    protected function whereBoolean(string $field, bool|int|string $value): void
    {
        $boolean = $this->booleanFilterValue($value);

        if ($boolean === null) {
            return;
        }

        $this->where($field, $boolean);
    }

    protected function whereInteger(string $field, int|string $value): void
    {
        $integer = $this->positiveInteger($value);

        if ($integer === null) {
            return;
        }

        $this->where($field, $integer);
    }

    protected function whereNullableInteger(string $field, int|string $value): void
    {
        $normalizedValue = strtolower(trim((string) $value));

        if (in_array($normalizedValue, ['null', 'none', 'unassigned'], true)) {
            $this->whereNull($field);

            return;
        }

        $this->whereInteger($field, $value);
    }

    protected function whereBooleanNull(string $field, bool|int|string $value): void
    {
        if ($this->booleanFilterValue($value) !== true) {
            return;
        }

        $this->whereNull($field);
    }

    protected function wherePositiveIntegerComparison(string $field, string $operator, int|string $value): void
    {
        $integer = $this->positiveInteger($value);

        if ($integer === null) {
            return;
        }

        $this->where($field, $operator, $integer);
    }

    protected function whereDateFrom(string $field, string $value): void
    {
        $date = $this->dateFilterValue($value);

        if ($date === null) {
            return;
        }

        $this->where($field, '>=', $date->startOfDay());
    }

    protected function whereDateTo(string $field, string $value): void
    {
        $date = $this->dateFilterValue($value);

        if ($date === null) {
            return;
        }

        $this->where($field, '<=', $date->endOfDay());
    }

    /**
     * @param  class-string<JsonResource>  $resource
     * @return array<string, mixed>
     */
    public static function paginatedResource(
        LengthAwarePaginator $paginator,
        string $resource,
        Request $request
    ): array {
        return [
            'items' => $resource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
