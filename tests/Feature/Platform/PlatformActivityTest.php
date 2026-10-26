<?php

use AulaX\Platform\Domain\OperatorRole;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R07.2, R07.4: any operator reads the platform log, filtered
 * by date, operator and event type; never the log of an institution.
 */
beforeEach(function () {
    $this->institution = tenant();
    DB::table('audit_entries')->insert(['institution_id' => $this->institution->id, 'occurred_at' => '2026-10-20 09:30:00+00', 'event_type' => 'students.student.registered', 'subject_type' => 'student', 'subject_id' => '1', 'origin' => 'web']);
    leaveTenant();
    DB::table('platform_audit_entries')->insert([
        ['occurred_at' => '2026-10-01 10:00:00+00', 'event_type' => 'platform.institution.deactivated', 'subject_type' => 'institution', 'subject_id' => '7', 'origin' => 'platform', 'operator' => 'operador #77', 'changes' => json_encode(['reason' => ['from' => null, 'to' => 'Falta de pago']])],
        ['occurred_at' => '2026-10-15 10:00:00+00', 'event_type' => 'identity.account.consolidated', 'subject_type' => 'account', 'subject_id' => '11', 'origin' => 'console', 'operator' => 'luis@aulax.ve', 'changes' => '{}'],
    ]);
    $this->support = signedInOperator('soporte@aulax.ve', OperatorRole::Support);
});

test('any operator reads the platform log, newest first, and the query is recorded', function () {
    $this->get(adminUrl('/bitacora'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Platform/Activity')
        ->where('log.entries.0.eventType', 'platform.login.succeeded')
        ->where('log.entries.1.eventType', 'identity.account.consolidated')
        ->where('log.entries.2.eventType', 'platform.institution.deactivated')
        ->where('log.entries.2.changes.0', ['field' => 'reason', 'from' => null, 'to' => 'Falta de pago'])
    );

    expect(DB::table('platform_audit_entries')->where('event_type', 'platform.activity.viewed')->value('operator'))->toBe("operador #{$this->support->value}");
});

test('filters by date, operator and event type', function () {
    $this->get(adminUrl('/bitacora?from=2026-10-10&to=2026-10-20'))->assertInertia(fn ($page) => $page
        ->where('log.total', 1)
        ->where('log.entries.0.subjectId', '11')
    );
    $this->get(adminUrl('/bitacora?operator=luis@aulax.ve'))->assertInertia(fn ($page) => $page->where('log.total', 1)->where('log.entries.0.origin', 'console'));
    $this->get(adminUrl('/bitacora?type=platform.institution'))->assertInertia(fn ($page) => $page->where('log.total', 1)->where('filters.type', 'platform.institution'));
    $this->get(adminUrl('/bitacora?type=100%'))->assertInertia(fn ($page) => $page->where('log.total', 0));
});

test('never shows the log of an institution (PLT-R07.4)', function () {
    $response = $this->get(adminUrl('/bitacora?type=students'))->assertOk();

    expect(json_encode($response->viewData('page')['props']))->not->toContain('students.student.registered');
});

test('without an operator session the log is not reachable', function () {
    $this->post(adminUrl('/logout'));

    $this->get(adminUrl('/bitacora'))->assertRedirect(adminUrl('/login'));
});
