<?php

use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * F1-02: proves the isolation is enforced by Postgres itself, not just the
 * Eloquent global scope — the scope is bypassed here on purpose.
 *
 * Run on its own with: php artisan test --group=rls
 */
pest()->group('rls');

/**
 * Leave every tenant context, as a request on the central domain or a
 * worker between jobs would be: no CurrentTenant (so the Eloquent scope
 * adds no filter at all) and no institution in the DB session.
 */
function withoutTenant(string $how): void
{
    app()->instance(CurrentTenant::class, new CurrentTenant);

    match ($how) {
        'clear' => RowLevelSecurity::clearInstitution(),
        'reset' => DB::statement('RESET '.RowLevelSecurity::SESSION_VARIABLE),
    };
}

// Plain strings, not closures: Pest resolves dataset closures before the
// test body runs, which would clear the session before tenant() sets it.
dataset('ways to have no tenant', [
    'cleared with clearInstitution()' => ['clear'],
    'reset by raw SQL' => ['reset'],
]);

test('a query without a tenant returns no rows', function (string $how) {
    $institutionA = tenant();
    User::factory()->for($institutionA)->create();
    $institutionB = tenant();
    User::factory()->for($institutionB)->create();

    withoutTenant($how);

    expect(User::count())->toBe(0);
    expect(DB::table('users')->count())->toBe(0);
    expect(DB::select('select count(*) as total from users')[0]->total)->toBe(0);
})->with('ways to have no tenant');

test('without a tenant nothing can be written', function (string $how) {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    withoutTenant($how);

    // In a savepoint, so the rejected insert doesn't abort the test's
    // surrounding transaction for the checks that follow.
    expect(fn () => DB::transaction(fn () => DB::table('users')->insert([
        'institution_id' => $institution->id,
        'name' => 'Sin tenant',
        'email' => 'sin-tenant@example.com',
        'password' => 'x',
    ])))->toThrow(QueryException::class, 'row-level security');

    // Invisible rows can't be updated or deleted either: nothing matches.
    expect(DB::table('users')->where('id', $user->id)->update(['name' => 'Cambiado']))->toBe(0);
    expect(DB::table('users')->where('id', $user->id)->delete())->toBe(0);
})->with('ways to have no tenant');

test('switching back to a tenant after clearing it shows only its rows again', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    RowLevelSecurity::clearInstitution();
    expect(DB::table('users')->count())->toBe(0);

    RowLevelSecurity::setInstitution($institution->id);
    expect(DB::table('users')->pluck('id')->all())->toBe([$user->id]);
});

test('the application connects with a role that can not bypass Row Level Security', function () {
    $role = DB::selectOne('select rolsuper, rolbypassrls from pg_roles where rolname = current_user');

    // A superuser or BYPASSRLS role silently ignores every policy, even FORCEd ones.
    expect($role->rolsuper)->toBeFalse();
    expect($role->rolbypassrls)->toBeFalse();
});
test('a tenant cannot read another tenant\'s users even without the Eloquent scope', function () {
    $institutionA = tenant();
    $userA = User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    User::factory()->for($institutionB)->create();

    // Currently "in" institution B (the last tenant() call set the RLS
    // session variable to it). Even bypassing the Eloquent global scope,
    // Postgres RLS must still hide institution A's row.
    $visibleIds = User::withoutGlobalScopes()->pluck('id');

    expect($visibleIds)->not->toContain($userA->id);
});

test('raw SQL is also subject to Row Level Security', function () {
    $institutionA = tenant();
    User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    User::factory()->for($institutionB)->create();

    $count = DB::table('users')->count();

    expect($count)->toBe(1);
});

test('a request on one subdomain cannot see users seeded for another', function () {
    $institutionA = tenant();
    $userA = User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    User::factory()->for($institutionB)->create();

    $this->post(tenantUrl($institutionB, '/login'), [
        'email' => $userA->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('inserting a row for a different institution than the session is rejected', function () {
    $institutionA = tenant();
    $institutionB = tenant(); // session now points at institution B

    expect(fn () => User::factory()->for($institutionA)->create())
        ->toThrow(QueryException::class);
});
