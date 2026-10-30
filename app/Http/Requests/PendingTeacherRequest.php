<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The teacher of a group of pending subjects (SPEC-016): the subject, the
 * grade of origin and the person, or none. Who may be one is the domain's
 * (S-09).
 */
class PendingTeacherRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'integer'],
            'origin_grade' => ['required', 'string', 'max:16'],
            'teacher' => ['nullable', 'integer'],
        ];
    }
}
