<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * SPEC-004 IDG-R09: the existing per-institution accounts become global
 * accounts with memberships, without merging anyone and without touching any
 * password. Runs the data migration over a database put back in the old shape.
 */
function accountsMigration(): object
{
    return require database_path('migrations/2026_10_15_141931_move_accounts_to_global_identity.php');
}

/**
 * A user row of the old per-institution model.
 */
function legacyUser(InstitutionModel $institution, string $email, string $name, array $overrides = []): int
{
    RowLevelSecurity::setInstitution($institution->id);

    return DB::table('users')->insertGetId([
        'institution_id' => $institution->id,
        'email' => $email,
        'name' => $name,
        'password' => $overrides['password'] ?? password_hash('secret-'.$email.$institution->id, PASSWORD_BCRYPT),
        'status' => $overrides['status'] ?? 'active',
        'email_verified_at' => array_key_exists('email_verified_at', $overrides) ? $overrides['email_verified_at'] : '2026-09-01 10:00:00',
        'created_at' => '2026-09-01 10:00:00',
        'updated_at' => '2026-09-01 10:00:00',
    ]);
}

function legacyRole(InstitutionModel $institution, int $userId, string $role): void
{
    DB::table('model_has_roles')->insert([
        'role_id' => Role::where('institution_id', $institution->id)->where('name', $role)->value('id'),
        'model_type' => 'App\Models\User',
        'model_id' => $userId,
        'institution_id' => $institution->id,
    ]);
}

/**
 * The old database: per-institution users, two institutions with roles.
 *
 * @return array{0: InstitutionModel, 1: InstitutionModel}
 */
function oldShapedDatabase(): array
{
    accountsMigration()->down();

    $norte = InstitutionModel::factory()->create(['subdomain' => 'norte']);
    $sur = InstitutionModel::factory()->create(['subdomain' => 'sur']);
    RolePermissionSeeder::seedForInstitution($norte);
    RolePermissionSeeder::seedForInstitution($sur);

    return [$norte, $sur];
}

function membershipOf(int $userId, InstitutionModel $institution): ?object
{
    RowLevelSecurity::setInstitution($institution->id);

    return DB::table('institution_memberships')->where('user_id', $userId)->first();
}

function accountRow(int $userId): ?object
{
    RowLevelSecurity::clearInstitution();

    return DB::table('users')->where('id', $userId)->first();
}

test('a regular account becomes a global account with an active membership, keeping its password', function () {
    [$norte] = oldShapedDatabase();
    $hash = password_hash('clave-de-ana', PASSWORD_BCRYPT);
    $ana = legacyUser($norte, 'ana@colegio.ve', 'Ana Pérez', ['password' => $hash]);

    accountsMigration()->up();

    expect(membershipOf($ana, $norte))->status->toBe('active')
        ->and(accountRow($ana)->password)->toBe($hash)
        ->and(accountRow($ana)->consolidation_state)->toBeNull();
});

test('a deactivated user keeps the account with a membership suspended by the institution', function () {
    [$norte] = oldShapedDatabase();
    $ana = legacyUser($norte, 'ana@colegio.ve', 'Ana Pérez', ['status' => 'inactive']);

    accountsMigration()->up();

    expect(membershipOf($ana, $norte))
        ->status->toBe('suspended')
        ->suspension_reason->toBe('institution');
});

test('emails are stored normalized', function () {
    [$norte] = oldShapedDatabase();
    $ana = legacyUser($norte, ' Ana@Colegio.VE ', 'Ana Pérez');

    accountsMigration()->up();

    expect(accountRow($ana)->email)->toBe('ana@colegio.ve');
});

test('accounts sharing an email stay separate, each with its own password and institution', function () {
    [$norte, $sur] = oldShapedDatabase();
    $inNorte = legacyUser($norte, 'ana@colegio.ve', 'Ana Pérez');
    $inSur = legacyUser($sur, 'ana@colegio.ve', 'ana  pérez');
    // Before migrating, users is still scoped by institution.
    RowLevelSecurity::setInstitution($norte->id);
    $hashes = [DB::table('users')->where('id', $inNorte)->value('password')];
    RowLevelSecurity::setInstitution($sur->id);
    $hashes[] = DB::table('users')->where('id', $inSur)->value('password');

    accountsMigration()->up();

    expect([accountRow($inNorte)->consolidation_state, accountRow($inSur)->consolidation_state])->toBe(['pending', 'pending'])
        ->and([accountRow($inNorte)->password, accountRow($inSur)->password])->toBe($hashes)
        ->and(membershipOf($inNorte, $norte))->not->toBeNull()
        ->and(membershipOf($inNorte, $sur))->toBeNull()
        ->and(membershipOf($inSur, $sur))->not->toBeNull();
});

test('accounts sharing an email but not the name are left for support to review', function () {
    [$norte, $sur] = oldShapedDatabase();
    $inNorte = legacyUser($norte, 'ana@colegio.ve', 'Ana Pérez');
    $inSur = legacyUser($sur, 'ana@colegio.ve', 'Ana Rodríguez');

    accountsMigration()->up();

    expect([accountRow($inNorte)->consolidation_state, accountRow($inSur)->consolidation_state])->toBe(['review', 'review']);
});

test('a user who never accepted the invitation becomes a pending invitation whose link still works', function () {
    [$norte] = oldShapedDatabase();
    $luis = legacyUser($norte, 'luis@colegio.ve', 'Luis Mora', ['email_verified_at' => null]);
    legacyRole($norte, $luis, 'Docente');
    $tokenHash = password_hash('token-del-correo', PASSWORD_BCRYPT);
    DB::table('password_reset_tokens')->insert(['email' => 'luis@colegio.ve', 'token' => $tokenHash, 'created_at' => '2026-10-13 08:00:00']);

    accountsMigration()->up();

    RowLevelSecurity::setInstitution($norte->id);
    $invitation = DB::table('invitations')->where('email', 'luis@colegio.ve')->sole();
    expect($invitation->status)->toBe('pending')
        ->and($invitation->name)->toBe('Luis Mora')
        ->and($invitation->token_hash)->toBe($tokenHash)
        ->and((new DateTimeImmutable($invitation->expires_at))->format('Y-m-d H:i'))->toBe('2026-10-20 08:00')
        ->and(json_decode($invitation->roles, true))->toBe(['Docente'])
        ->and(accountRow($luis))->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $luis)->exists())->toBeFalse();
});

test('a pending invitation whose link was lost is migrated already expired, ready to be resent', function () {
    [$norte] = oldShapedDatabase();
    legacyUser($norte, 'luis@colegio.ve', 'Luis Mora', ['email_verified_at' => null]);
    $this->freezeTime();

    accountsMigration()->up();

    RowLevelSecurity::setInstitution($norte->id);
    expect(new DateTimeImmutable(DB::table('invitations')->where('email', 'luis@colegio.ve')->value('expires_at')))
        ->toBeLessThanOrEqual(new DateTimeImmutable);
});

test('the migration can be undone while no account has been consolidated', function () {
    [$norte, $sur] = oldShapedDatabase();
    $ana = legacyUser($norte, 'ana@colegio.ve', 'Ana Pérez', ['status' => 'inactive']);
    $luis = legacyUser($sur, 'luis@colegio.ve', 'Luis Mora', ['email_verified_at' => null]);
    legacyRole($sur, $luis, 'Docente');
    accountsMigration()->up();

    accountsMigration()->down();

    RowLevelSecurity::setInstitution($norte->id);
    expect(DB::table('users')->where('email', 'ana@colegio.ve')->value('status'))->toBe('inactive');
    RowLevelSecurity::setInstitution($sur->id);
    $restored = DB::table('users')->where('email', 'luis@colegio.ve')->sole();
    expect($restored->email_verified_at)->toBeNull()
        ->and($restored->institution_id)->toBe($sur->id);
});
