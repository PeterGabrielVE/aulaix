<?php

use AulaX\Audit\Application\AuditContext;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\Route;

/**
 * SPEC-005 AUD-R01.3: who made a change and from where is resolved from the
 * running process, never from what the user sends.
 */
function contextSnapshot(): array
{
    $context = app(AuditContext::class);
    $origin = $context->origin();

    return ['actor' => $context->actorId(), 'origin' => $origin->kind, 'ip' => $origin->ip, 'operator' => $origin->operator];
}

beforeEach(function () {
    Route::middleware('web')->get('/_test/audit-context', fn () => contextSnapshot());
});

it('attributes a web change to the authenticated account and the client IP', function () {
    $user = User::factory()->make(['id' => 42]);

    $this->actingAs($user)
        ->withServerVariables(['REMOTE_ADDR' => '190.202.10.5'])
        ->getJson('/_test/audit-context')
        ->assertExactJson(['actor' => 42, 'origin' => 'web', 'ip' => '190.202.10.5', 'operator' => null]);
});

it('records a web change by a guest with no actor', function () {
    $this->getJson('/_test/audit-context')
        ->assertJson(['actor' => null, 'origin' => 'web']);
});

it('attributes a change outside any request to the system', function () {
    expect(contextSnapshot())->toBe(['actor' => null, 'origin' => 'system', 'ip' => null, 'operator' => null]);
});

it('attributes a console change to the operator who ran the command', function () {
    app(AuditContext::class)->actAsConsoleOperator('soporte.ana');

    expect(contextSnapshot())->toBe(['actor' => null, 'origin' => 'console', 'ip' => null, 'operator' => 'soporte.ana']);
});
