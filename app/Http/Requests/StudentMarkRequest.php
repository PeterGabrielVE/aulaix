<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a literal (Primaria) or a report (Inicial) of a student in a
 * period (SPEC-011). The rules themselves are the domain's (S-09); the
 * lengths here only cap what reaches it.
 */
class StudentMarkRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:literal,report'],
            'period' => ['required', 'in:1,2,3,final'],
            'literal' => ['nullable', 'string', 'max:5'],
            'description' => ['nullable', 'string', 'max:10000'],
            'report' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => 'Elige un lapso del 1 al 3, o el final.',
        ];
    }
}
