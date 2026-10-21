<?php

namespace App\Http\Requests;

use AulaX\Tenancy\Domain\InstitutionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The shape of the institution form (SPEC-006). Uniqueness, the catalog's
 * levels and parishes are the use case's call (S-09). Nothing here names an
 * institution: the open one is the one that changes (INS-R07.1).
 */
class InstitutionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'dea_code' => ['nullable', 'string', 'max:20'],
            'rif' => ['nullable', 'string', 'max:12'],
            'parish_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'type' => ['required', Rule::enum(InstitutionType::class)],
            'levels' => ['required', 'array', 'list'],
            'levels.*' => ['string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Elige si el plantel es público, privado o subvencionado.',
            'type.enum' => 'Elige si el plantel es público, privado o subvencionado.',
            'levels.required' => 'Elige al menos un nivel.',
        ];
    }
}
