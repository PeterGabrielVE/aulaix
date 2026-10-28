<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The grades of an activity (SPEC-011): student id => a grade, "NP" or
 * nothing, for at most 60 students at a time (S-12, denial of service). The
 * scale is the domain's (S-09).
 */
class ActivityGradesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'max:60'],
            'grades.*' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grades.max' => 'Guarda como máximo 60 notas a la vez.',
        ];
    }
}
