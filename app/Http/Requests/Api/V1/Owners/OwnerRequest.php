<?php

namespace App\Http\Requests\Api\V1\Owners;

use App\Models\Owner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', Owner::class);
        }

        $owner = $this->route('owner');

        return $owner instanceof Owner
            && $actor->can('update', $owner);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $owner = $this->route('owner');
        $ownerId = $owner instanceof Owner ? $owner->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('owners', 'email')->ignore($ownerId),
            ],
            'is_active' => ['required', 'boolean'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('owners', 'document_number')->ignore($ownerId),
            ],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
