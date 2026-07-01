<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeResourceMutation();
    }

    protected function authorizeResourceMutation(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', $this->modelClass());
        }

        $model = $this->routeModel();

        return $model !== null
            && $actor->can('update', $model);
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    abstract protected function routeParameter(): string;

    protected function routeModel(): ?Model
    {
        $model = $this->route($this->routeParameter());
        $modelClass = $this->modelClass();

        return $model instanceof $modelClass ? $model : null;
    }

    protected function routeModelKey(): mixed
    {
        return $this->routeModel()?->getKey();
    }

    protected function lowerTrimmedString(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $this->merge([
            $field => Str::lower(trim((string) $this->input($field))),
        ]);
    }

    protected function upperTrimmedString(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $this->merge([
            $field => Str::upper(trim((string) $this->input($field))),
        ]);
    }

    protected function upperSlugString(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $this->merge([
            $field => Str::upper(Str::slug((string) $this->input($field), '-')),
        ]);
    }

    protected function emptyStringToNull(string $field): void
    {
        if (! $this->has($field) || $this->input($field) !== '') {
            return;
        }

        $this->merge([$field => null]);
    }

    protected function missingOrEmptyStringToNull(string $field): void
    {
        if ($this->has($field) && $this->input($field) !== '') {
            return;
        }

        $this->merge([$field => null]);
    }

    protected function emptyValueToArray(string $field): void
    {
        if (! $this->has($field) || ! in_array($this->input($field), [null, ''], true)) {
            return;
        }

        $this->merge([$field => []]);
    }

    protected function normalizeArrayKeysToLower(string $field): void
    {
        if (! $this->has($field) || ! is_array($this->input($field))) {
            return;
        }

        $normalized = [];

        foreach ($this->input($field) as $key => $value) {
            $normalized[Str::lower((string) $key)] = $value;
        }

        $this->merge([$field => $normalized]);
    }
}
