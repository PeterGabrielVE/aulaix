<?php

use AulaX\Platform\Domain\OperatorRole;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-017 RSP-R08.1 – R08.4, NF05: approving a transfer while the destination
 * institution is inactive is refused without modifying either institution,
 * reveals no deactivation reason, preserves the original 15-day deadline, and
 * succeeds once reactivated before expiry.
 */
beforeEach(function () {
    Notification::fake();
    $this->origin = tenant(['name' => 'Colegio Norte', 'subdomain' => 'norte']);
    $this->originAdmin = userWithRole($this->origin, 'Administrador');
    $this->actingAs($this->originAdmin)->post(tenantUrl($this->origin, '/estudiantes'), [
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => 'V11915432100', 'allergies' => 'Penicilina',
        'guardians' => [[
            'given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id', 'document' => 'V15432100',
            'phone' => '0414', 'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true,
        ]],
    ])->assertSessionHasNoErrors();

    $this->destination = tenant(['name' => 'Colegio Sur', 'subdomain' => 'sur']);
    $this->destinationAdmin = userWithRole($this->destination, 'Administrador');

    // Destination requests the transfer while active.
    $this->actingAs($this->destinationAdmin)
        ->post(tenantUrl($this->destination, '/traslados'), ['identification_type' => 'school_id', 'identification' => 'V11915432100'])
        ->assertSessionHasNoErrors();
    $this->transferId = (int) DB::table('transfer_requests')->max('id');

    leaveTenant();
    $this->superAdmin = storedOperator('jefa@aulax.ve', OperatorRole::SuperAdmin);
});

function setDestinationActiveFromPanel(Institution $destination, bool $active, string $reason): void
{
    leaveTenant();
    test()->flushSession();
    panelLogIn('jefa@aulax.ve', 'clave-de-operador', currentCode(test()->superAdmin))->assertRedirect(route('platform.home'));
    test()->put(adminUrl("/planteles/{$destination->id}/estado"), ['active' => $active, 'reason' => $reason])
        ->assertSessionHasNoErrors();
    test()->post(adminUrl('/logout'));
    test()->flushSession();
}

test('no reason: approving a transfer to an inactive destination is refused without changes and preserves the request', function () {
    setDestinationActiveFromPanel($this->destination, false, 'Mora de tres meses en suscripción');

    useTenant($this->origin);
    $response = $this->actingAs($this->originAdmin)
        ->from(tenantUrl($this->origin, '/traslados'))
        ->post(tenantUrl($this->origin, "/traslados/{$this->transferId}/aprobar"));

    $response->assertSessionHasErrors(['transfer' => 'El plantel destino no puede recibir el traslado por ahora.']);
    expect(json_encode(session('errors')->all()))->not->toContain('Mora');

    // Origin student stays active and request stays pending.
    useTenant($this->origin);
    expect(DB::table('students')->value('status'))->toBe('active')
        ->and(DB::table('student_withdrawals')->count())->toBe(0);

    // Nothing was written into the inactive destination, and the transfer stays pending.
    useTenant($this->destination);
    expect(DB::table('students')->count())->toBe(0)
        ->and(DB::table('guardians')->count())->toBe(0)
        ->and(DB::table('transfer_requests')->where('id', $this->transferId)->value('status'))->toBe('pending');
});

test('approving succeeds once the destination is reactivated before the original 15-day deadline', function () {
    setDestinationActiveFromPanel($this->destination, false, 'Suspensión temporal');

    $this->travel(5)->days();
    useTenant($this->origin);
    $this->actingAs($this->originAdmin)
        ->post(tenantUrl($this->origin, "/traslados/{$this->transferId}/aprobar"))
        ->assertSessionHasErrors('transfer');

    $this->travel(5)->days();
    setDestinationActiveFromPanel($this->destination, true, 'Pago al día');

    useTenant($this->origin);
    $this->actingAs($this->originAdmin)
        ->post(tenantUrl($this->origin, "/traslados/{$this->transferId}/aprobar"))
        ->assertSessionHasNoErrors();

    useTenant($this->origin);
    expect(DB::table('students')->value('status'))->toBe('withdrawn');

    useTenant($this->destination);
    expect(DB::table('students')->where('identification', 'V11915432100')->value('status'))->toBe('active')
        ->and(DB::table('transfer_requests')->where('id', $this->transferId)->value('status'))->toBe('approved');
});

test('a pending request to an inactive destination still expires after its original 15 days', function () {
    setDestinationActiveFromPanel($this->destination, false, 'Cierre');

    $this->travel(16)->days();
    setDestinationActiveFromPanel($this->destination, true, 'Reapertura');

    useTenant($this->origin);
    $this->actingAs($this->originAdmin)
        ->post(tenantUrl($this->origin, "/traslados/{$this->transferId}/aprobar"))
        ->assertSessionHasErrors('status');

    useTenant($this->destination);
    expect(DB::table('students')->count())->toBe(0);
});
