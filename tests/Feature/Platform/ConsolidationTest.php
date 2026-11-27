<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Platform\Domain\OperatorRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R06 over HTTP: the doubtful cases resolved from the panel,
 * previewed first, with the same rules as the console command (IDG-NF07)
 * and the operator of the session in the platform log.
 */
beforeEach(function () {
    Notification::fake();
    $this->older = userWithRole(tenant(['name' => 'Colegio Norte']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana Pérez', 'consolidation_state' => 'review']);
    $this->newer = userWithRole(tenant(['name' => 'Colegio Sur']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana María Pérez', 'consolidation_state' => 'review']);
    leaveTenant();
    $this->luis = signedInOperator('luis@aulax.ve', OperatorRole::Support);
    $this->accounts = [$this->older->id, $this->newer->id];
});

test('lists the cases with their accounts and institutions (PLT-R06.1)', function () {
    $this->get(adminUrl('/consolidaciones'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Platform/Consolidations')
        ->has('cases', 1)
        ->where('cases.0.email', 'ana@example.com')
        ->where('cases.0.accounts.0.name', 'Ana Pérez')
        ->where('cases.0.accounts.0.institutions', ['Colegio Norte'])
    );
});

test('opening a case records the query (PLT-R07.1)', function () {
    $this->get(adminUrl("/consolidaciones/{$this->newer->id}"))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Platform/Consolidation')
        ->where('case.email', 'ana@example.com')
        ->has('case.accounts', 2)
    );

    $entry = DB::table('platform_audit_entries')->where('event_type', 'platform.consolidation_case.viewed')->sole();
    expect($entry->operator)->toBe("operador #{$this->luis->value}");

    $this->get(adminUrl('/consolidaciones/999999'))->assertNotFound();
});

test('a preview shows the changes and changes nothing (PLT-R06.3)', function () {
    $this->from(adminUrl("/consolidaciones/{$this->older->id}"))
        ->post(adminUrl("/consolidaciones/{$this->older->id}/vista-previa"), ['accounts' => $this->accounts, 'decision' => 'consolidate', 'reason' => 'Cédula verificada', 'verification_ref' => 'TICKET-1'])
        ->assertRedirect(adminUrl("/consolidaciones/{$this->older->id}"))
        ->assertSessionHas('platform.consolidation_preview', fn (array $preview) => $preview['plan']['survivorId'] === $this->older->id
            && $preview['plan']['affectedIds'] === [$this->newer->id]
            && $preview['input']['reason'] === 'Cédula verificada');

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2);
});

test('consolidating needs a reason and the proof of ownership, and changes nothing without them (PLT-R06.2)', function () {
    $this->from(adminUrl("/consolidaciones/{$this->older->id}"))
        ->post(adminUrl("/consolidaciones/{$this->older->id}/resolver"), ['accounts' => $this->accounts, 'decision' => 'consolidate', 'reason' => 'Verificada', 'verification_ref' => ''])
        ->assertSessionHasErrors('general');

    $this->post(adminUrl("/consolidaciones/{$this->older->id}/resolver"), ['accounts' => $this->accounts, 'decision' => 'separate', 'reason' => ''])
        ->assertSessionHasErrors('reason');

    expect(User::query()->where('email', 'ana@example.com')->pluck('consolidation_state')->unique()->all())->toBe(['review']);
});

test('resolving merges the case and records it with the operator of the session (PLT-R06.5)', function () {
    $this->post(adminUrl("/consolidaciones/{$this->older->id}/resolver"), ['accounts' => $this->accounts, 'decision' => 'consolidate', 'reason' => 'Cédula verificada', 'verification_ref' => 'TICKET-1'])
        ->assertRedirect(adminUrl('/consolidaciones'))
        ->assertSessionHasNoErrors();

    $entry = DB::table('platform_audit_entries')->where('event_type', 'identity.account.consolidated')->sole();
    expect(User::query()->where('email', 'ana@example.com')->sole()->id)->toBe($this->older->id)
        ->and($entry->origin)->toBe('platform')
        ->and($entry->operator)->toBe("operador #{$this->luis->value}")
        ->and(json_decode($entry->changes, true)['verification_ref']['to'])->toBe('TICKET-1');
});

test('a case that changed since it was opened is refused untouched (PLT-R06.4)', function () {
    DB::table('users')->where('id', $this->newer->id)->update(['consolidation_state' => 'pending']);

    $this->post(adminUrl("/consolidaciones/{$this->older->id}/resolver"), ['accounts' => $this->accounts, 'decision' => 'separate', 'reason' => 'Son dos personas'])
        ->assertSessionHasErrors('general');

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2)
        ->and(DB::table('platform_audit_entries')->where('event_type', 'identity.consolidation.refused')->value('operator'))->toBe("operador #{$this->luis->value}");
});

test('resolving needs a fresh TOTP', function () {
    $this->travel(11)->minutes();
    $this->get(adminUrl('/'));

    $this->from(adminUrl("/consolidaciones/{$this->older->id}"))
        ->post(adminUrl("/consolidaciones/{$this->older->id}/resolver"), ['accounts' => $this->accounts, 'decision' => 'separate', 'reason' => 'Son dos personas'])
        ->assertRedirect(adminUrl('/confirmar-codigo'));

    expect(User::query()->where('email', 'ana@example.com')->pluck('consolidation_state')->unique()->all())->toBe(['review']);
});

test('without an operator session, nothing of the cases is reachable', function () {
    $this->post(adminUrl('/logout'));

    $this->get(adminUrl('/consolidaciones'))->assertRedirect(adminUrl('/login'));
    $this->post(adminUrl("/consolidaciones/{$this->older->id}/resolver"), ['accounts' => $this->accounts, 'decision' => 'separate', 'reason' => 'x'])->assertRedirect(adminUrl('/login'));
});
