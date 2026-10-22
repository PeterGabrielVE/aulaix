<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a withdrawal (SPEC-009 EST-R06.1 – R06.3). The date, the
 * reason and their rules are the domain's (S-09).
 */
class WithdrawalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'withdrawn_on' => ['nullable', 'string', 'max:10'],
            'reason' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
