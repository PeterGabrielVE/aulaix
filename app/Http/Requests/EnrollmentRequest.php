<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of an enrollment (SPEC-010): who, where and from when, with the
 * reasons a rule may ask for. The rules themselves are the domain's (S-09).
 */
class EnrollmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student' => ['required', 'integer'],
            'school_year' => ['required', 'integer'],
            'section' => ['required', 'integer'],
            'date' => ['nullable', 'string', 'max:10'],
            'over_capacity_reason' => ['nullable', 'string', 'max:500'],
            'age_exception' => ['nullable', 'string', 'max:500'],
        ];
    }
}
