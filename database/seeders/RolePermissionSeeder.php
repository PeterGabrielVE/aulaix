<?php

namespace Database\Seeders;

use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\Permission as PermissionName;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
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
    public static function seedForInstitution(Institution $institution): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($institution->id);

        // Every permission exists, also those no base role carries
        // (editar-notas-lapso-cerrado, granted person by person).
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        // The base roles and their permissions come from the domain
        // (BaseRole), so they are defined in a single place.
        foreach (BaseRole::cases() as $base) {
            Role::findOrCreate($base->value, 'web')->syncPermissions(
                array_map(fn (PermissionName $permission) => $permission->value, $base->permissions()),
            );
        }
    }
}
