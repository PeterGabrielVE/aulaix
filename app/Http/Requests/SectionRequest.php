<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a section (SPEC-008): grade, letter and capacity. The grades
 * of the institution, the unique letter and the cap are the domain's call
 * (S-09).
 */
class SectionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grade' => ['nullable', 'string', 'max:16'],
            'letter' => ['nullable', 'string', 'max:2'],
            'capacity' => ['nullable'],
            'capacity_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
