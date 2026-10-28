<?php

use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-015 RLT-R01.4, NF01: through PgBouncer in transaction mode, with one
 * server connection per database, two clients take turns on the same
 * PostgreSQL connection. Only phpunit.pgbouncer.xml goes through the pool.
 */
beforeEach(function () {
    if ((int) config('database.connections.pgsql.port') !== 6432) {
        $this->markTestSkipped('Only through PgBouncer: phpunit.pgbouncer.xml.');
    }

    $this->norte = TenantContext::of(InstitutionMapper::toDomain(Institution::factory()->create()));
});

/**
 * A second client of the pool, as another request on another PHP worker.
 */
function anotherPoolClient(): PDO
{
    $config = config('database.connections.pgsql');

    return new PDO("pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}", $config['username'], $config['password']);
}

function settingSeenBy(PDO $client): string
{
    return (string) $client->query("select current_setting('app.current_institution_id', true)")->fetchColumn();
}

test('what one client sets inside its transaction is never seen by the next client of the pool', function () {
    app(TenantActivator::class)->within($this->norte, fn () => DB::select('select 1'));

    expect(settingSeenBy(anotherPoolClient()))->toBe('');
});

test('a setting for the whole connection, the old way, does reach the next client: why the pool needs ADR 0011', function () {
    DB::select("select set_config('app.current_institution_id', ?, false)", [(string) $this->norte->institutionId->value]);

    try {
        expect(settingSeenBy(anotherPoolClient()))->toBe((string) $this->norte->institutionId->value);
    } finally {
        DB::select("select set_config('app.current_institution_id', '', false)");
    }
});
