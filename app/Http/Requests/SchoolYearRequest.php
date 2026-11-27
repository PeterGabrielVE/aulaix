<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a school year's dates: eight Y-m-d dates. Whether they make
 * sense together (order, duration, overlaps) is the domain's call (S-09).
 */
class SchoolYearRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d'],
            'terms' => ['required', 'array', 'size:3'],
            'terms.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'terms.*.ends_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function terms(): array
    {
        return array_map(
            fn (array $term) => [$term['starts_on'], $term['ends_on']],
            array_values($this->validated('terms')),
        );
    }
}
