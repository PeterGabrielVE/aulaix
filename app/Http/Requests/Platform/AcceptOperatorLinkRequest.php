<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * SPEC-014 PLT-R03.1, R03.7: a password when joining, and the first code of
 * the authenticator app.
 */
class AcceptOperatorLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'code' => ['required', 'string', 'max:6'],
        ];
    }
}
