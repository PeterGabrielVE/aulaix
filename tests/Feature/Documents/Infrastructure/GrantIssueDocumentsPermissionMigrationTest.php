<?php

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPEC-012 DOC-R08.1: emitir-documentos granted to Administrador, Director and
 * Coordinador in existing institutions, with clean rollback.
 */
test('grants emitir-documentos to Administrador, Director and Coordinador in existing institutions and rolls back cleanly', function () {
    $institution = tenant(['subdomain' => 'doc-perm-mig']);
    RolePermissionSeeder::seedForInstitution($institution);

    $migration = require base_path('database/migrations/2026_11_27_000003_add_document_permissions_to_existing_institutions.php');

    // Roll back first to simulate a pre-SPEC-012 institution, then run up().
    $migration->down();
    expect(Permission::query()->where('name', 'emitir-documentos')->exists())->toBeFalse();

    $migration->up();

    app(PermissionRegistrar::class)->setPermissionsTeamId($institution->id);
    $roles = Role::query()->where('institution_id', $institution->id)->get()->keyBy('name');

    expect($roles['Administrador']->hasPermissionTo('emitir-documentos'))->toBeTrue()
        ->and($roles['Director']->hasPermissionTo('emitir-documentos'))->toBeTrue()
        ->and($roles['Coordinador']->hasPermissionTo('emitir-documentos'))->toBeTrue()
        ->and($roles['Docente']->hasPermissionTo('emitir-documentos'))->toBeFalse()
        ->and($roles['Representante']->hasPermissionTo('emitir-documentos'))->toBeFalse();
});
