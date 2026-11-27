<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPEC-004 A-09: moving the account model must not orphan role assignments
 * stored under the old class name.
 */
function morphAliasMigration(): object
{
    return require database_path('migrations/2026_10_15_145946_map_user_morph_type_to_alias.php');
}

test('roles assigned under the old class name are kept after the move', function () {
    $user = userWithRole(tenant(), 'Docente');
    morphAliasMigration()->down();
    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type'))->toBe('App\Models\User');

    morphAliasMigration()->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('model_type'))->toBe('user')
        ->and(AccountModel::find($user->id)->hasRole('Docente'))->toBeTrue();
});
