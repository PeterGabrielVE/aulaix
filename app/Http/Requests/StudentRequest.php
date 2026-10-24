<?php

namespace App\Http\Requests;

use AulaX\Students\Application\StudentForm\GuardianFields;
use AulaX\Students\Application\StudentForm\StudentFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of the student form (SPEC-009): types and lengths only. Which
 * data are required, the cédula escolar and the guardians' rules are the
 * domain's call, so every error comes from one place (S-09).
 */
class StudentRequest extends FormRequest
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
            'sex' => ['nullable', 'string', 'max:8'],
            'birth_date' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:1'],
            'identification_type' => ['nullable', 'string', 'max:16'],
            'identification' => ['nullable', 'string', 'max:20'],
            'birth_country' => ['nullable', 'string', 'max:2'],
            'birth_state_id' => ['nullable', 'integer'],
            'parish_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_conditions' => ['nullable', 'string', 'max:1000'],
            'disability' => ['nullable', 'string', 'max:1000'],
            'guardians' => ['present', 'array', 'list', 'max:6'],
            'guardians.*.guardian_id' => ['nullable', 'integer'],
            'guardians.*.given_names' => ['nullable', 'string', 'max:120'],
            'guardians.*.family_names' => ['nullable', 'string', 'max:120'],
            'guardians.*.document_type' => ['nullable', 'string', 'max:16'],
            'guardians.*.document' => ['nullable', 'string', 'max:30'],
            'guardians.*.phone' => ['nullable', 'string', 'max:30'],
            'guardians.*.email' => ['nullable', 'string', 'max:255'],
            'guardians.*.address' => ['nullable', 'string', 'max:500'],
            'guardians.*.relationship' => ['nullable', 'string', 'max:16'],
            'guardians.*.is_legal' => ['boolean'],
            'guardians.*.is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guardians.present' => 'El estudiante necesita al menos un representante legal.',
            'guardians.max' => 'Un estudiante tiene como máximo 6 representantes.',
        ];
    }

    public function fields(): StudentFields
    {
        return new StudentFields(
            givenNames: $this->validated('given_names'),
            familyNames: $this->validated('family_names'),
            sex: $this->validated('sex'),
            birthDate: $this->validated('birth_date'),
            nationality: $this->validated('nationality'),
            identificationType: $this->validated('identification_type'),
            identification: $this->validated('identification'),
            birthCountry: $this->validated('birth_country'),
            birthStateId: $this->integerOrNull('birth_state_id'),
            parishId: $this->integerOrNull('parish_id'),
            address: $this->validated('address'),
            emergencyPhone: $this->validated('emergency_phone'),
            guardians: array_map(fn (array $guardian) => new GuardianFields(
                guardianId: isset($guardian['guardian_id']) ? (int) $guardian['guardian_id'] : null,
                givenNames: $guardian['given_names'] ?? null,
                familyNames: $guardian['family_names'] ?? null,
                documentType: $guardian['document_type'] ?? null,
                document: $guardian['document'] ?? null,
                phone: $guardian['phone'] ?? null,
                email: $guardian['email'] ?? null,
                address: $guardian['address'] ?? null,
                relationship: (string) ($guardian['relationship'] ?? ''),
                isLegal: filter_var($guardian['is_legal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                isPrimary: filter_var($guardian['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ), array_values($this->validated('guardians'))),
        );
    }

    private function integerOrNull(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null ? null : (int) $value;
    }
}
