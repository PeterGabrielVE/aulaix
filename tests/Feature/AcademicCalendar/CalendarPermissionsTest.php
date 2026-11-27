<?php

use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\Permission;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * SPEC-007 CAL-R05.4, R06.5: the calendar permissions, in new and existing
 * institutions.
 */
function permissionsMigration(): object
{
    return require database_path('migrations/2026_10_19_100000_add_calendar_permissions_to_existing_institutions.php');
}

test('the seeded roles carry exactly the permissions of their base role', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);

    foreach (BaseRole::cases() as $base) {
        $seeded = Role::findByName($base->value)->permissions->pluck('name')->sort()->values()->all();
        $expected = collect($base->permissions())->map(fn (Permission $permission) => $permission->value)->sort()->values()->all();

        expect($seeded)->toBe($expected, "{$base->value} no tiene los permisos de BaseRole.");
    }
});

test('existing institutions get gestionar-calendario for Administrador and Director', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);
    permissionsMigration()->down();

    expect(DB::table('permissions')->where('name', 'gestionar-calendario')->exists())->toBeFalse();

    permissionsMigration()->up();

    foreach (['Administrador' => true, 'Director' => true, 'Coordinador' => false, 'Docente' => false] as $role => $has) {
        expect(Role::findByName($role)->hasPermissionTo('gestionar-calendario'))->toBe($has);
    }
});

test('the special permission exists but no role has it', function () {
    $institution = tenant();
    permissionsMigration()->up();

    expect(DB::table('permissions')->where('name', 'editar-notas-lapso-cerrado')->exists())->toBeTrue()
        ->and(DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'editar-notas-lapso-cerrado')
            ->exists())->toBeFalse();
});
