<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;

/**
 * SPEC-004 IDG-R11, IDG-NF07: support resolves the doubtful cases from the
 * console, with a restricted, audited and simulable tool.
 */
beforeEach(function () {
    config(['identity.consolidation_operators' => ['soporte.luis']]);
    Notification::fake();
});

/**
 * Two accounts with the same email but different names: marked for review.
 *
 * @return array{0: User, 1: User}
 */
function reviewCase(): array
{
    $older = userWithRole(tenant(['name' => 'Colegio Norte']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana Pérez', 'consolidation_state' => 'review']);
    $newer = userWithRole(tenant(['name' => 'Colegio Sur']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana María Pérez', 'consolidation_state' => 'review']);
    leaveTenant();

    return [$older, $newer];
}

function resolveCase(array $accounts, array $options = []): PendingCommand
{
    return test()->artisan('identity:consolidation:resolve', [
        'accounts' => array_map(fn (User $account) => $account->id, $accounts),
        '--operator' => 'soporte.luis',
        '--decision' => 'consolidate',
        '--reason' => 'Cédula verificada por teléfono',
        '--verification-ref' => 'TICKET-123',
        ...$options,
    ]);
}

test('support lists the cases marked for review, and only those', function () {
    [$older, $newer] = reviewCase();
    userWithRole(tenant(), 'Docente', ['email' => 'otro@example.com', 'consolidation_state' => 'pending']);
    leaveTenant();

    $this->artisan('identity:consolidation:list')
        ->expectsOutputToContain('ana@example.com')
        ->expectsOutputToContain("#{$older->id} Ana Pérez (Colegio Norte)")
        ->expectsOutputToContain("#{$newer->id} Ana María Pérez (Colegio Sur)")
        ->doesntExpectOutputToContain('otro@example.com')
        ->assertSuccessful();
});

test('only authorized operators may resolve a case', function () {
    $case = reviewCase();

    resolveCase($case, ['--operator' => 'intruso'])->assertFailed();

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2);
});

test('the operator, the decision and a reason are required', function (string $missing) {
    $case = reviewCase();

    resolveCase($case, [$missing => ''])->assertFailed();

    expect(User::query()->where('email', 'ana@example.com')->pluck('consolidation_state')->unique()->all())->toBe(['review']);
})->with(['--operator', '--decision', '--reason']);

test('a dry run shows the changes without making any', function () {
    [$older, $newer] = reviewCase();

    resolveCase([$older, $newer], ['--dry-run' => true])
        ->expectsOutputToContain("#{$newer->id}")
        ->expectsOutputToContain('Simulación')
        ->assertSuccessful();

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2)
        ->and(DB::table('platform_audit_entries')->count())->toBe(0);
});

test('accounts that are not, or no longer, under review are refused untouched', function () {
    [$older, $newer] = reviewCase();
    DB::table('users')->where('id', $newer->id)->update(['consolidation_state' => 'pending']);

    resolveCase([$older, $newer])->assertFailed();

    // Not every account sharing the email: also refused.
    $third = User::factory()->create(['email' => 'ana@example.com', 'consolidation_state' => 'review']);
    DB::table('users')->where('id', $newer->id)->update(['consolidation_state' => 'review']);
    resolveCase([$older, $newer])->assertFailed();

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(3);
});

test('consolidating needs proof of ownership and merges everything into the oldest account', function () {
    [$older, $newer] = reviewCase();

    resolveCase([$older, $newer], ['--verification-ref' => ''])->assertFailed();
    resolveCase([$older, $newer])->assertSuccessful();

    $account = User::query()->where('email', 'ana@example.com')->sole();
    expect($account->id)->toBe($older->id)
        ->and($account->consolidation_state)->toBeNull()
        ->and(DB::table('institution_memberships')->where('user_id', $older->id)->count())->toBe(2);
});

test('accounts kept separate stay apart for good', function () {
    [$older, $newer] = reviewCase();

    resolveCase([$older, $newer], ['--decision' => 'separate', '--verification-ref' => ''])->assertSuccessful();

    expect(User::query()->where('email', 'ana@example.com')->pluck('consolidation_state')->unique()->all())->toBe(['separated']);

    // Each password still opens its own institution; the owner can not merge them.
    $this->actingAs($older)->withSession(['auth.password_confirmed_at' => time()])
        ->post(centralUrl('/cuenta/unificar/codigo'))
        ->assertSessionHasErrors('code');
});

test('every resolution is recorded with its operator, decision, reason and accounts', function () {
    [$older, $newer] = reviewCase();

    resolveCase([$older, $newer])->assertSuccessful();

    $entry = DB::table('platform_audit_entries')->where('event_type', 'identity.account.consolidated')->sole();
    expect($entry->origin)->toBe('console')
        ->and($entry->operator)->toBe('soporte.luis')
        ->and(json_decode($entry->changes, true))->toMatchArray([
            'merged_accounts' => ['from' => [$newer->id], 'to' => []],
            'decision' => ['from' => null, 'to' => 'consolidate'],
            'reason' => ['from' => null, 'to' => 'Cédula verificada por teléfono'],
            'verification_ref' => ['from' => null, 'to' => 'TICKET-123'],
        ]);
});
