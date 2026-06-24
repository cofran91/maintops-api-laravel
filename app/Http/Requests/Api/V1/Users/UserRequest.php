<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Enums\SystemRole;
use App\Models\User;
use App\Rules\Users\AssignableUserRole;
use App\Rules\Users\AssignableUserWorkshop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', User::class);
        }

        $target = $this->route('user');

        return $target instanceof User
            && $actor->can('update', $target);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower((string) $this->input('email')),
            ]);
        }

        if (! $this->has('workshop_id') || $this->input('workshop_id') === '') {
            $this->merge(['workshop_id' => null]);
        }

    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $targetId = $target instanceof User ? $target->getKey() : null;

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
