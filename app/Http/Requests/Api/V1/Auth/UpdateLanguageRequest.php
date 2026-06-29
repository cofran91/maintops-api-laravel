<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\Localization\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Locale::supported())],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('locale')) {
            return;
        }

        $this->merge([
            'locale' => Locale::normalize((string) $this->input('locale')) ?? $this->input('locale'),
        ]);
    }
}
