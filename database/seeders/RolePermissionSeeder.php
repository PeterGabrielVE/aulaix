<?php

namespace Database\Seeders;

use App\Models\Institution;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the base roles/permissions for a single institution (F1-06).
 *
 * Roles and permissions are scoped per institution via
 * spatie/laravel-permission's "teams" feature (config/permission.php:
 * teams => true, team_foreign_key => institution_id): the same role name
 * ("Docente") can exist independently in every institution.
 */
class RolePermissionSeeder
{
    private const PERMISSIONS = [
        'gestionar-usuarios',
        'gestionar-roles',
        'ver-estudiantes',
        'gestionar-calificaciones',
        'ver-calificaciones',
    ];

    private const ROLES = [
        'Administrador' => self::PERMISSIONS,
        'Docente' => ['ver-estudiantes', 'gestionar-calificaciones', 'ver-calificaciones'],
        'Representante' => ['ver-calificaciones'],
        'Estudiante' => ['ver-calificaciones'],
    ];

    public static function seedForInstitution(Institution $institution): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($institution->id);

        $permissions = collect(self::PERMISSIONS)->mapWithKeys(
            fn (string $name) => [$name => Permission::findOrCreate($name, 'web')]
        );

        foreach (self::ROLES as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions->only($permissionNames)->values());
        }
    }
}
