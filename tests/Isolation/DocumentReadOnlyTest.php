<?php

use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantForVerificationHandler;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantQuery;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-012 DOC-R07.5 (ADR 0015): outside RefreshDatabase (real top-level
 * transaction, as in production and PgBouncer), `forReadOnlyRequest()` sets
 * `SET TRANSACTION READ ONLY` so PostgreSQL itself rejects any write with
 * SQLSTATE 25006.
 */
test('forReadOnlyRequest enforces PostgreSQL SET TRANSACTION READ ONLY on inactive institutions', function () {
    $inactive = Institution::factory()->create(['subdomain' => 'doc-iso-ro', 'status' => 'inactive']);
    $context = app(ResolveTenantForVerificationHandler::class)(new ResolveTenantQuery('doc-iso-ro'));

    $caught = null;
    $rlsBeforeWrite = null;

    app(TenantActivator::class)->forReadOnlyRequest($context, function () use (&$caught, &$rlsBeforeWrite) {
        $rlsBeforeWrite = DB::selectOne("SELECT current_setting('app.current_institution_id', true) AS id")->id;

        try {
            DB::table('students')->insert([
                'given_names' => 'Bloqueado',
                'family_names' => 'Readonly',
                'sex' => 'male',
                'birth_date' => '2015-01-01',
                'nationality' => 'V',
                'identification_type' => 'national_id',
                'identification' => 'V99887766',
                'status' => 'active',
            ]);
        } catch (QueryException $e) {
            $caught = $e;
        }
    });

    expect((int) $rlsBeforeWrite)->toBe($inactive->id)
        ->and($caught)->toBeInstanceOf(QueryException::class)
        ->and($caught->getCode())->toBe('25006');
});
