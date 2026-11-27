<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-004 IDG-R11, IDG-NF07: support resolves the doubtful cases from the
 * console, with a restricted, audited and simulable tool.
 */
beforeEach(function () {
    // SPEC-014 PLT-R06.6, V3: the operator is an active operator of the panel.
    storedOperator('luis@aulax.ve', OperatorRole::Support);
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
        '--operator' => 'luis@aulax.ve',
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
        ->and($entry->operator)->toBe('luis@aulax.ve')
        ->and(json_decode($entry->changes, true))->toMatchArray([
            'merged_accounts' => ['from' => [$newer->id], 'to' => []],
            'decision' => ['from' => null, 'to' => 'consolidate'],
            'reason' => ['from' => null, 'to' => 'Cédula verificada por teléfono'],
            'verification_ref' => ['from' => null, 'to' => 'TICKET-123'],
        ]);
});

test('accepts only an active operator of the panel (PLT-R06.6, V3)', function () {
    [$older, $newer] = reviewCase();

    resolveCase([$older, $newer], ['--operator' => 'LUIS@aulax.ve', '--dry-run' => true])->assertSuccessful();
});

test('refuses an inactive, pending or unknown operator, and records the refusal (V3)', function (string $operator) {
    $repository = app(OperatorRepository::class);
    $repository->save(Operator::invite('Pendiente', OperatorEmail::fromString('pendiente@aulax.ve'), OperatorRole::Support));
    $inactive = $repository->byId(storedOperator('baja@aulax.ve', OperatorRole::Support));
    $inactive->deactivate();
    $repository->save($inactive);
    [$older, $newer] = reviewCase();

    resolveCase([$older, $newer], ['--operator' => $operator])->assertFailed();

    $entry = DB::table('platform_audit_entries')->where('event_type', 'identity.consolidation.refused')->sole();
    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2)
        ->and($entry->origin)->toBe('console')
        ->and($entry->operator)->toBe($operator)
        ->and(json_decode($entry->changes, true)['refusal']['to'])->toBe('El operador no está autorizado para resolver consolidaciones.');
})->with(['baja@aulax.ve', 'pendiente@aulax.ve', 'nadie@aulax.ve', 'soporte.luis']);
