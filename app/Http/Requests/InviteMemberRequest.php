<?php

namespace App\Http\Requests;

use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The shape of an invitation. Who may grant which roles, and whether the
 * email may be invited, is decided by InviteMemberHandler.
 */
class InviteMemberRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            ...self::roleRules(),
        ];
    }

    /**
     * A person may hold several roles in the same institution (e.g. Docente
     * and Representante), each one existing in it.
     *
     * @return array<string, array<mixed>>
     */
    public static function roleRules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(app(RoleCatalog::class)->available()->names())],
        ];
    }
}
