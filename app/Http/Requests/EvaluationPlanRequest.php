<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a plan of activities (SPEC-011): at most 30, each with its
 * name, date and weight. The rules themselves are the domain's (S-09).
 */
class EvaluationPlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activities' => ['required', 'array', 'max:30'],
            'activities.*.id' => ['nullable', 'integer'],
            'activities.*.name' => ['nullable', 'string', 'max:100'],
            'activities.*.date' => ['nullable', 'string', 'max:10'],
            'activities.*.weight' => ['nullable', 'string', 'max:10'],
            'confirm_removals' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activities.max' => 'Un plan tiene como máximo 30 actividades.',
        ];
    }
}
