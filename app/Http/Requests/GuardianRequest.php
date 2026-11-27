<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a guardian's correction (SPEC-009 EST-R03.8). Its rules are
 * the domain's (S-09).
 */
class GuardianRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'given_names' => ['nullable', 'string', 'max:120'],
            'family_names' => ['nullable', 'string', 'max:120'],
            'document_type' => ['nullable', 'string', 'max:16'],
            'document' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
