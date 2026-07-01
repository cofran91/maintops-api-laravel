<?php

namespace App\Http\Requests\Api\V1\Owners;

use App\Http\Requests\Api\V1\ResourceRequest;
use App\Models\Owner;
use Illuminate\Validation\Rule;

class OwnerRequest extends ResourceRequest
{
    protected function modelClass(): string
    {
        return Owner::class;
    }

    protected function routeParameter(): string
    {
        return 'owner';
    }

    protected function prepareForValidation(): void
    {
        $this->lowerTrimmedString('email');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $ownerId = $this->routeModelKey();

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
