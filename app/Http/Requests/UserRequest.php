<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Create/update a user from the administrator's user management screens.
 */
class UserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $editedUser = $this->route('user');
        $editingSelf = $editedUser?->is($this->user());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)
                    ->where('institution_id', app(CurrentTenant::class)->id())
                    ->ignore($editedUser),
            ],
            'role' => [
                'required', 'string', Rule::in(self::assignableRoles()),
                // An administrator demoting themselves could leave the
                // institution with nobody able to manage users.
                function (string $attribute, mixed $value, Closure $fail) use ($editingSelf) {
                    if ($editingSelf && $value !== 'Administrador') {
                        $fail('No puedes quitarte a ti mismo el rol de Administrador.');
                    }
                },
            ],
            'status' => [
                Rule::requiredIf($editedUser !== null),
                Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE]),
                function (string $attribute, mixed $value, Closure $fail) use ($editingSelf) {
                    if ($editingSelf && $value !== User::STATUS_ACTIVE) {
                        $fail('No puedes desactivar tu propia cuenta.');
                    }
                },
            ],
        ];
    }

    /**
     * Role names that exist in the current institution, in the same
     * priority order used for post-login redirects.
     *
     * @return array<int, string>
     */
    public static function assignableRoles(): array
    {
        $existing = Role::query()
            ->where(config('permission.column_names.team_foreign_key'), app(CurrentTenant::class)->id())
            ->pluck('name')
            ->all();

        return array_values(array_intersect(array_keys(User::HOME_ROUTES), $existing));
    }
}
