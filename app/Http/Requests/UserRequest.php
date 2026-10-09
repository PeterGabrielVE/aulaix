<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
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
            // A user may hold several roles in the same institution (e.g.
            // Docente and Representante); each institution's account is
            // separate, so roles never carry over to another one.
            'roles' => [
                'required', 'array', 'min:1',
                // Someone removing their own access could leave the
                // institution with nobody able to manage users, or (since
                // only an Administrador can grant that role) with no
                // Administrador ever again.
                function (string $attribute, mixed $value, Closure $fail) use ($editingSelf) {
                    if (! $editingSelf) {
                        return;
                    }

                    if ($this->user()->hasRole('Administrador') && ! in_array('Administrador', (array) $value, true)) {
                        $fail('No puedes quitarte a ti mismo el rol de Administrador.');
                    } elseif (! self::grantsUserManagement((array) $value)) {
                        $fail('No puedes quitarte a ti mismo la gestión de usuarios.');
                    }
                },
                // Director also manages users, but must not be able to make
                // anyone (themselves included) an Administrador, or take the
                // role away from one.
                function (string $attribute, mixed $value, Closure $fail) use ($editedUser) {
                    if ($this->user()->hasRole('Administrador')) {
                        return;
                    }

                    $wantsAdministrador = in_array('Administrador', (array) $value, true);
                    $isAdministrador = (bool) $editedUser?->hasRole('Administrador');

                    if ($wantsAdministrador !== $isAdministrador) {
                        $fail('Solo un Administrador puede asignar o quitar el rol de Administrador.');
                    }
                },
            ],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(self::assignableRoles())],
            'status' => [
                Rule::requiredIf($editedUser !== null),
                Rule::enum(UserStatus::class),
                function (string $attribute, mixed $value, Closure $fail) use ($editingSelf) {
                    if ($editingSelf && $value !== UserStatus::Active->value) {
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

    /**
     * Whether any of the given roles of the current institution carries the
     * permission to manage users.
     *
     * @param  array<int, mixed>  $roleNames
     */
    private static function grantsUserManagement(array $roleNames): bool
    {
        return Role::query()
            ->where(config('permission.column_names.team_foreign_key'), app(CurrentTenant::class)->id())
            ->whereIn('name', array_filter($roleNames, 'is_string'))
            ->whereRelation('permissions', 'name', 'gestionar-usuarios')
            ->exists();
    }
}
