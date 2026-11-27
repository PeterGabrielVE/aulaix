<?php

use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Infrastructure\Notifications\OperatorLinkMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * SPEC-014 PLT-R02 and V3 (step 1): the first SuperAdmin is created from
 * the server, receives its link, and the platform log keeps who ran it.
 */
beforeEach(function () {
    Notification::fake();
    leaveTenant();
});

test('creates a pending SuperAdmin, sends its link and records it with console as origin', function () {
    $this->artisan('platform:create-super-admin', ['name' => 'Ana Rivas', 'email' => 'ana@aulax.ve'])
        ->expectsOutputToContain('ana@aulax.ve')
        ->assertSuccessful();

    $operator = app(OperatorRepository::class)->byEmail(OperatorEmail::fromString('ana@aulax.ve'));
    $entry = DB::table('platform_audit_entries')->where('event_type', 'platform.operator.invited')->sole();

    expect($operator->role)->toBe(OperatorRole::SuperAdmin)
        ->and($operator->status)->toBe(OperatorStatus::Pending)
        ->and($entry->origin)->toBe('console')
        ->and($entry->operator)->toStartWith('consola: ')
        ->and(json_decode($entry->changes, true)['email']['to'])->toBe('ana@aulax.ve')
        ->and($entry->changes)->not->toContain('token');

    Notification::assertSentOnDemand(OperatorLinkMail::class);
});

test('refuses an email that already belongs to an operator', function () {
    $this->artisan('platform:create-super-admin', ['name' => 'Ana Rivas', 'email' => 'ana@aulax.ve'])->assertSuccessful();

    $this->artisan('platform:create-super-admin', ['name' => 'Otra', 'email' => 'ANA@aulax.ve'])
        ->expectsOutputToContain('Ese correo ya pertenece a un operador.')
        ->assertFailed();

    expect(DB::table('platform_operators')->count())->toBe(1);
});
