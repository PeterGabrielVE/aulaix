<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-004 / ADR 0006 A: with an institution active, the database only shows
 * the people who are members of it; with none active (central domain) the
 * global accounts are readable. Raw SQL on purpose.
 */
pest()->group('rls');

function rawAccount(string $email, ?string $consolidation = null): int
{
    $id = DB::selectOne("select nextval(pg_get_serial_sequence('users', 'id')) as id")->id;

    DB::table('users')->insert([
        'id' => $id,
        'email' => $email,
        'name' => 'Persona',
        'password' => 'hash',
        'account_state' => 'active',
        'consolidation_state' => $consolidation,
    ]);

    return $id;
}

function rawMembership(int $accountId, int $institutionId): void
{
    DB::table('institution_memberships')->insert(['institution_id' => $institutionId, 'user_id' => $accountId, 'status' => 'active']);
}

test('an institution only sees the people who are its members', function () {
    $norte = tenant();
    $member = rawAccount('miembro@colegio.ve');
    rawMembership($member, $norte->id);
    rawAccount('ajeno@colegio.ve');

    expect(DB::table('users')->pluck('id')->all())->toBe([$member]);
});

test('a member of another institution is invisible, even with raw SQL', function () {
    $sur = tenant();
    $other = rawAccount('sur@colegio.ve');
    rawMembership($other, $sur->id);

    tenant();

    expect(DB::select('select count(*) as total from users where id = ?', [$other])[0]->total)->toBe(0);
});

test('an account can be created while an institution is active, before it becomes a member', function () {
    tenant();

    expect(rawAccount('nueva@colegio.ve'))->toBeInt();
});

test('every account is readable with no institution active', function () {
    tenant();
    rawAccount('uno@colegio.ve');
    rawAccount('dos@colegio.ve');

    RowLevelSecurity::clearInstitution();

    expect(DB::table('users')->count())->toBe(2);
});

test('two regular accounts can not share an email', function () {
    RowLevelSecurity::clearInstitution();
    rawAccount('ana@colegio.ve');

    expect(fn () => rawAccount('ana@colegio.ve'))->toThrow(QueryException::class, 'users_email_regular_unique');
});

test('historical accounts awaiting consolidation may share an email', function () {
    RowLevelSecurity::clearInstitution();
    rawAccount('ana@colegio.ve', 'pending');

    expect(rawAccount('ana@colegio.ve', 'pending'))->toBeInt();
});
