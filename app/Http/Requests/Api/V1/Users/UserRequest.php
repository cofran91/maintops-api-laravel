<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Enums\SystemRole;
use App\Http\Requests\Api\V1\ResourceRequest;
use App\Models\User;
use App\Rules\Users\AssignableUserRole;
use App\Rules\Users\AssignableUserWorkshop;
use App\Support\Localization\Locale;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends ResourceRequest
{
    protected function modelClass(): string
    {
        return User::class;
    }

    protected function routeParameter(): string
    {
        return 'user';
    }

    protected function prepareForValidation(): void
    {
        $this->lowerTrimmedString('email');

        if ($this->has('preferred_locale')) {
            $this->merge([
                'preferred_locale' => Locale::normalize((string) $this->input('preferred_locale')) ?? $this->input('preferred_locale'),
            ]);
        }

        $this->missingOrEmptyStringToNull('workshop_id');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = $this->routeModel();
        $targetId = $this->routeModelKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetId),
            ],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'preferred_locale' => ['sometimes', 'string', Rule::in(Locale::supported())],
            'role' => [
                'required',
                'string',
                Rule::in(SystemRole::values()),
                new AssignableUserRole(
                    $this->user(),
                    $target instanceof User ? $target : null,
                    $this->isMethod('post'),
                ),
            ],
            'is_active' => ['required', 'boolean'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('users', 'document_number')->ignore($targetId),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'workshop_id' => [
                'nullable',
                'integer',
                Rule::exists('workshops', 'id')->whereNull('deleted_at'),
                new AssignableUserWorkshop(
                    $this->user(),
                    is_string($this->input('role')) ? $this->input('role') : '',
                ),
            ],
        ];
    }
}
