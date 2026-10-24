<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member's roles and status in the institution. Name and email are not
 * part of it: they belong to the account's owner (IDG-R05.5).
 */
class UpdateMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...InviteMemberRequest::roleRules(),
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
