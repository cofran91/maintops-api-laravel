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
