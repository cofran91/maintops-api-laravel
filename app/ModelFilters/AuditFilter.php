<?php

namespace App\ModelFilters;

use Illuminate\Database\Eloquent\Builder;

final class AuditFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected array $allowedFilters = [
        'search',
        'event',
        'user_id',
        'user_type',
        'auditable_id',
        'auditable_type',
        'ip_address',
        'url',
        'tags',
        'created_from',
        'created_to',
    ];

    public function search(string $value): void
    {
        $this->where(function (Builder $query) use ($value): void {
            $query
                ->where('event', 'like', '%'.$value.'%')
                ->orWhere('auditable_type', 'like', '%'.$value.'%')
                ->orWhere('user_type', 'like', '%'.$value.'%')
                ->orWhere('url', 'like', '%'.$value.'%')
                ->orWhere('ip_address', 'like', '%'.$value.'%')
                ->orWhere('tags', 'like', '%'.$value.'%');
        });
    }

    public function event(string $value): void
    {
        $this->where('event', $value);
    }

    public function userId(mixed $value): void
    {
        $this->where('user_id', $value);
    }

    public function userType(string $value): void
    {
        $this->where('user_type', $value);
    }

    public function auditableId(mixed $value): void
    {
        $this->where('auditable_id', $value);
    }

    public function auditableType(string $value): void
    {
        $this->where('auditable_type', $value);
    }

    public function ipAddress(string $value): void
    {
        $this->where('ip_address', $value);
    }

    public function url(string $value): void
    {
        $this->where('url', 'like', '%'.$value.'%');
    }

    public function tags(string $value): void
    {
        $this->where('tags', 'like', '%'.$value.'%');
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
