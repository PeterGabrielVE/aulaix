<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a change of section or grade (SPEC-010 MAT-R04): where,
 * from when and why. The rules themselves are the domain's (S-09).
 */
class PlacementChangeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'integer'],
            'date' => ['nullable', 'string', 'max:10'],
            'reason' => ['nullable', 'string', 'max:500'],
            'over_capacity_reason' => ['nullable', 'string', 'max:500'],
            'age_exception' => ['nullable', 'string', 'max:500'],
        ];
    }
}
