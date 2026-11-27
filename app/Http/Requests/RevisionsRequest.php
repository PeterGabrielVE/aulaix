<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The revisions of a subject (SPEC-016): student id => a grade, "NP" or
 * nothing, for at most 60 students at a time (S-12). The scale and who may
 * take one are the domain's (S-09).
 */
class RevisionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'revisions' => ['required', 'array', 'max:60'],
            'revisions.*' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'revisions.max' => 'Guarda como máximo 60 revisiones a la vez.',
        ];
    }
}
