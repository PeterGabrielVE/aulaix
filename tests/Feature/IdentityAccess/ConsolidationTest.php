<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\AccountsConsolidatedNotice;
use AulaX\IdentityAccess\Infrastructure\Notifications\ConsolidationCode;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-004 IDG-R09.4, R09.7, R11.8: the owner of historical accounts sharing
 * an email merges them into one, proving they receive mail there.
 */
function historicalAccounts(string $state = 'pending'): array
{
    $norte = tenant(['name' => 'Colegio Norte']);
    $older = userWithRole($norte, 'Docente', ['email' => 'ana@example.com', 'password' => 'clave-norte', 'consolidation_state' => $state]);
    $sur = tenant(['name' => 'Colegio Sur']);
    $newer = userWithRole($sur, 'Representante', ['email' => 'ana@example.com', 'password' => 'clave-sur', 'consolidation_state' => $state]);

    // Logged in with the newer account's password, and recently confirmed (IDG-NF05).
    leaveTenant();
    test()->actingAs($newer)->withSession(['auth.password_confirmed_at' => time()]);

    return [$older, $newer, $norte, $sur];
}

function requestCode(): string
{
    Notification::fake();
    test()->post(centralUrl('/cuenta/unificar/codigo'))->assertSessionHasNoErrors();

    $code = null;
    Notification::assertSentOnDemand(ConsolidationCode::class, function (ConsolidationCode $mail, array $channels, object $notifiable) use (&$code) {
        $code = $mail->code;

        return $notifiable->routes['mail'] === 'ana@example.com';
    });

    return $code;
}

function consolidate(string $code, string $password = 'una-sola-clave'): TestResponse
{
    return test()->post(centralUrl('/cuenta/unificar'), [
        'code' => $code,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

test('historical accounts are merged into the oldest one, with a code sent by email', function () {
    [$older, $newer, $norte, $sur] = historicalAccounts();

    consolidate(requestCode())->assertSessionHasNoErrors()->assertRedirect(centralUrl('/planteles'));

    $account = User::query()->where('email', 'ana@example.com')->sole();
    expect($account->id)->toBe($older->id)
        ->and($account->consolidation_state)->toBeNull()
        ->and(Hash::check('una-sola-clave', $account->password))->toBeTrue();
    $this->assertAuthenticatedAs($account);

    // Both memberships, each with its own roles, now belong to it.
    $this->get(centralUrl('/planteles'))->assertInertia(fn ($page) => $page
        ->where('institutions.0.name', 'Colegio Norte')
        ->where('institutions.1.name', 'Colegio Sur')
        ->where('canConsolidate', false)
    );
    useTenant($sur);
    expect($account->fresh()->getRoleNames()->all())->toBe(['Representante']);
    useTenant($norte);
    expect($account->fresh()->getRoleNames()->all())->toBe(['Docente']);
});

test('no historical password survives the merge', function () {
    [$older, $newer] = historicalAccounts();

    consolidate(requestCode());

    leaveTenant();
    expect(User::query()->find($newer->id))->toBeNull()
        ->and(Hash::check('clave-norte', User::query()->find($older->id)->password))->toBeFalse();
});

test('the merge closes the merged accounts\' sessions, tells the owner and is recorded', function () {
    config(['session.driver' => 'database']);
    [$older, $newer] = historicalAccounts();
    DB::table('sessions')->insert(['id' => 'old-sur', 'user_id' => $newer->id, 'payload' => '', 'last_activity' => time()]);

    consolidate(requestCode());

    expect(DB::table('sessions')->where('id', 'old-sur')->exists())->toBeFalse();
    Notification::assertSentOnDemand(AccountsConsolidatedNotice::class);
    $entry = DB::table('platform_audit_entries')->where('event_type', 'identity.account.consolidated')->sole();
    expect($entry->subject_id)->toBe((string) $older->id)
        ->and(json_decode($entry->changes, true))->toEqual(['merged_accounts' => ['from' => [$newer->id], 'to' => []]]);
});

test('a wrong code is rejected, and five wrong codes burn it', function () {
    historicalAccounts();
    $code = requestCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    consolidate($wrong)->assertSessionHasErrors('code');
    foreach (range(1, 4) as $attempt) {
        consolidate($wrong);
    }

    consolidate($code)->assertSessionHasErrors('code');
    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2);
});

test('a code expires after 15 minutes', function () {
    historicalAccounts();
    $code = requestCode();

    $this->travel(16)->minutes();
    // Typed the password again just now: only the code is too old.
    $this->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

    consolidate($code)->assertSessionHasErrors('code');
});

test('accounts marked for review can never be merged by their owner', function () {
    historicalAccounts('review');

    $this->post(centralUrl('/cuenta/unificar/codigo'))->assertSessionHasErrors('code');
    consolidate('123456')->assertSessionHasErrors('code');

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2);
});

test('merging asks for the password again after ten minutes', function () {
    historicalAccounts();
    $this->withSession(['auth.password_confirmed_at' => time() - 601]);

    $this->get(centralUrl('/cuenta/unificar'))->assertRedirect(centralUrl('/confirmar-contrasena'));

    $this->post(centralUrl('/confirmar-contrasena'), ['password' => 'clave-norte'])->assertSessionHasErrors('password');
    $this->post(centralUrl('/confirmar-contrasena'), ['password' => 'clave-sur'])->assertRedirect(centralUrl('/cuenta/unificar'));

    $this->get(centralUrl('/cuenta/unificar'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Central/Consolidate')
        ->where('institutions', ['Colegio Norte', 'Colegio Sur'])
    );
});
