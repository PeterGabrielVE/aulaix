<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPEC-005 AUD-R06.3: Administrador and Director may view the audit log; no
 * other base role may.
 */
test('only administrador and director may view the audit log', function (string $role, bool $mayView) {
    $user = userWithRole(tenant(), $role);

    expect($user->hasPermissionTo('ver-bitacora'))->toBe($mayView);
})->with([
    'Administrador' => ['Administrador', true],
    'Director' => ['Director', true],
    'Coordinador' => ['Coordinador', false],
    'Docente' => ['Docente', false],
    'Representante' => ['Representante', false],
    'Estudiante' => ['Estudiante', false],
]);

test('the permission migration gives the audit log to the managers of existing institutions', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    Permission::where('name', 'ver-bitacora')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($director->fresh()->getAllPermissions()->pluck('name'))->not->toContain('ver-bitacora');

    $migration = require database_path('migrations/2026_10_11_215320_add_audit_log_permission_to_existing_institutions.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($director->fresh()->getAllPermissions()->pluck('name'))->toContain('ver-bitacora');
});
