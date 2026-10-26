<?php

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-014 PLT-R07.1 (and SPEC-005): what a platform operator does is
 * recorded in the platform log, with «platform» as origin and the operator
 * as actor.
 */
test('records an operator action in the platform log, with its origin and operator', function () {
    app(AuditContext::class)->actAsPlatformOperator(5);

    DB::transaction(fn () => app(AuditTrail::class)->recordForPlatform(
        AuditEvent::of('platform.institution.viewed', AuditSubject::of('institution', 9)),
    ));

    $entry = DB::table('platform_audit_entries')->sole();

    expect($entry->origin)->toBe('platform')
        ->and($entry->operator)->toBe('operador #5')
        ->and($entry->actor_user_id)->toBeNull()
        ->and(DB::table('audit_entries')->count())->toBe(0);
});
