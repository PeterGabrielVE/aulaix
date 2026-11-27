<?php

use AulaX\Tenancy\Application\ResolveTenant\InstitutionNotFound;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantForVerificationHandler;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantQuery;
use AulaX\Tenancy\Application\TenantActivator;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-012 DOC-R07.5 (ADR 0015): public verification resolves active and
 * inactive institutions and scopes RLS inside forReadOnlyRequest(), rolling
 * back unconditionally when finished.
 */
test('resolves active and inactive institutions in a PostgreSQL read-only transaction', function () {
    $active = tenant(['subdomain' => 'doc-ro-activo', 'status' => 'active']);
    $inactive = tenant(['subdomain' => 'doc-ro-inactivo', 'status' => 'inactive']);
    leaveTenant();

    $activeContext = app(ResolveTenantForVerificationHandler::class)(new ResolveTenantQuery('doc-ro-activo'));
    $inactiveContext = app(ResolveTenantForVerificationHandler::class)(new ResolveTenantQuery('doc-ro-inactivo'));

    expect($activeContext->institutionId->value)->toBe($active->id)
        ->and($inactiveContext->institutionId->value)->toBe($inactive->id)
        ->and(fn () => app(ResolveTenantForVerificationHandler::class)(new ResolveTenantQuery('no-existe')))
        ->toThrow(InstitutionNotFound::class);

    $setting = app(TenantActivator::class)->forReadOnlyRequest(
        $inactiveContext,
        fn () => DB::selectOne("SELECT current_setting('app.current_institution_id', true) AS id")->id,
    );

    expect((int) $setting)->toBe($inactive->id)
        ->and(DB::selectOne("SELECT current_setting('app.current_institution_id', true) AS id")->id)->toBe('');
});
