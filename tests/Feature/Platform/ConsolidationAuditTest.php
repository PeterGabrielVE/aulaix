<?php

use AulaX\Platform\Domain\OperatorRole;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-017 RSP-R07.1 – R07.3: listing consolidation cases and running previews
 * are recorded in the platform audit log with only account identifiers, never
 * names, emails or institution names.
 */
beforeEach(function () {
    $this->older = userWithRole(tenant(['name' => 'Colegio Norte']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana Pérez', 'consolidation_state' => 'review']);
    $this->newer = userWithRole(tenant(['name' => 'Colegio Sur']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana María Pérez', 'consolidation_state' => 'review']);
    leaveTenant();
    $this->luis = signedInOperator('luis@aulax.ve', OperatorRole::Support);
});

test('listing consolidation cases records operator, date, IP and account ids without PII', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
        ->get(adminUrl('/consolidaciones'))
        ->assertOk();

    $entry = DB::table('platform_audit_entries')->where('event_type', 'platform.consolidation_cases.listed')->sole();
    $changes = json_decode($entry->changes, true);

    expect($entry->operator)->toBe("operador #{$this->luis->value}")
        ->and($entry->ip)->toBe('198.51.100.77')
        ->and($changes['accounts']['to'])->toBe([$this->older->id, $this->newer->id])
        ->and($entry->changes)->not->toContain('ana@example.com')
        ->and($entry->changes)->not->toContain('Ana Pérez')
        ->and($entry->changes)->not->toContain('Colegio Norte');
});

test('successful panel preview records operator, decision and account ids without PII', function () {
    $this->post(adminUrl("/consolidaciones/{$this->older->id}/vista-previa"), [
        'accounts' => [$this->older->id, $this->newer->id],
        'decision' => 'consolidate',
        'reason' => 'Cédula verificada',
        'verification_ref' => 'TICKET-42',
    ])->assertRedirect();

    $entry = DB::table('platform_audit_entries')->where('event_type', 'identity.consolidation.previewed')->sole();
    $changes = json_decode($entry->changes, true);

    expect($entry->operator)->toBe("operador #{$this->luis->value}")
        ->and($changes)->toEqual([
            'decision' => ['from' => null, 'to' => 'consolidate'],
            'accounts' => ['from' => null, 'to' => [$this->older->id, $this->newer->id]],
        ])
        ->and($entry->changes)->not->toContain('ana@example.com')
        ->and($entry->changes)->not->toContain('Ana Pérez')
        ->and($entry->changes)->not->toContain('Colegio Norte');
});
