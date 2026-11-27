<?php

use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\RecoveryCodeHasher;
use AulaX\Platform\Domain\RecoveryCodes;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-014 PLT-NF02, R03.2 (V4); SPEC-017 ADR 0014: operators, their links
 * and their recovery codes in the database, with every secret encrypted or
 * hashed, updates guarded by OperatorLock, and each link used once.
 */
function savedOperator(string $email = 'ana@aulax.ve'): Operator
{
    $repository = app(OperatorRepository::class);
    $operator = Operator::invite('Ana Rivas', OperatorEmail::fromString($email), OperatorRole::SuperAdmin);
    $id = $repository->save($operator);

    return $repository->byId($id);
}

test('saves and loads an operator, with its TOTP secret encrypted at rest', function () {
    $repository = app(OperatorRepository::class);
    $operator = savedOperator();
    app(OperatorLock::class)->lockOperator($operator->id);
    $operator->activate('password-hash', 'JBSWY3DPEHPK3PXP', step: 100);
    $repository->save($operator);

    $loaded = $repository->byEmail(OperatorEmail::fromString('ANA@aulax.ve'));

    expect($loaded->id->equals($operator->id))->toBeTrue()
        ->and($loaded->canLogIn())->toBeTrue()
        ->and($loaded->totpSecret)->toBe('JBSWY3DPEHPK3PXP')
        ->and($loaded->totpLastStep)->toBe(100)
        ->and(DB::table('platform_operators')->value('totp_secret'))->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($repository->byEmail(OperatorEmail::fromString('nadie@aulax.ve')))->toBeNull();
});

test('keeps only the hashes of the recovery codes, and their use', function () {
    $repository = app(OperatorRepository::class);
    $hasher = app(RecoveryCodeHasher::class);
    $operator = savedOperator();
    app(OperatorLock::class)->lockOperator($operator->id);
    $plain = array_map(fn (int $n) => "codigo-{$n}", range(1, 10));
    $repository->saveRecoveryCodes($operator->id, RecoveryCodes::fromPlain($plain, $hasher));
    $repository->saveRecoveryCodes($operator->id, $repository->recoveryCodes($operator->id)->use('codigo-3', $hasher));

    expect($repository->recoveryCodes($operator->id)->remaining())->toBe(9)
        ->and(DB::table('platform_operator_recovery_codes')->pluck('code_hash')->implode(','))->not->toContain('codigo');
});

test('save without the lock fails', function () {
    $repository = app(OperatorRepository::class);
    $operator = savedOperator();
    $operator->deactivate();

    expect(fn () => $repository->save($operator))->toThrow(LogicException::class);
});

test('saveRecoveryCodes without the lock fails', function () {
    $repository = app(OperatorRepository::class);
    $hasher = app(RecoveryCodeHasher::class);
    $operator = savedOperator();
    $codes = RecoveryCodes::fromPlain(array_map(fn (int $n) => "codigo-{$n}", range(1, 10)), $hasher);

    expect(fn () => $repository->saveRecoveryCodes($operator->id, $codes))->toThrow(LogicException::class);
});

test('persists code failures and blocked_until', function () {
    $repository = app(OperatorRepository::class);
    $operator = savedOperator();
    app(OperatorLock::class)->lockOperator($operator->id);
    $at = new DateTimeImmutable('2026-11-27T10:00:00+00:00');

    for ($i = 1; $i <= 10; $i++) {
        $operator->failCode($at->modify("+{$i} seconds"), "198.51.100.{$i}");
    }

    $repository->save($operator);
    $loaded = $repository->byId($operator->id);

    expect($loaded->codeFailures)->toHaveCount(10)
        ->and($loaded->codeFailures[0]->ip)->toBe('198.51.100.1')
        ->and($loaded->codeFailures[9]->ip)->toBe('198.51.100.10')
        ->and($loaded->blockedUntil)->toEqual($at->modify('+10 seconds +15 minutes'));
});

test('a new link revokes the pending ones of the same operator, and keeps only the token hash', function () {
    $invitations = app(OperatorInvitationRepository::class);
    $operator = savedOperator();
    $now = new DateTimeImmutable;
    $first = $invitations->issue(OperatorInvitation::issue($operator->id, InvitationPurpose::Onboarding, hash('sha256', 'primero'), $now));
    $invitations->issue(OperatorInvitation::issue($operator->id, InvitationPurpose::Onboarding, hash('sha256', 'segundo'), $now));

    expect($invitations->byTokenHash(hash('sha256', 'primero'))->isUsableAt($now))->toBeFalse()
        ->and($invitations->byTokenHash(hash('sha256', 'segundo'))->isUsableAt($now))->toBeTrue()
        ->and($first)->toBeInt()
        ->and(DB::table('platform_operator_invitations')->pluck('token_hash')->implode(','))->not->toContain('primero');
});

test('a link is consumed once, even by two racing requests', function () {
    $invitations = app(OperatorInvitationRepository::class);
    $operator = savedOperator();
    $now = new DateTimeImmutable;
    $invitations->issue(OperatorInvitation::issue($operator->id, InvitationPurpose::Onboarding, hash('sha256', 't'), $now));
    $first = $invitations->byTokenHash(hash('sha256', 't'));
    $second = $invitations->byTokenHash(hash('sha256', 't'));

    expect($invitations->consume($first, $now))->toBeTrue()
        ->and($invitations->consume($second, $now))->toBeFalse();
});

test('revokes every link of an operator', function () {
    $invitations = app(OperatorInvitationRepository::class);
    $operator = savedOperator();
    $now = new DateTimeImmutable;
    $invitations->issue(OperatorInvitation::issue($operator->id, InvitationPurpose::MfaReset, hash('sha256', 'r'), $now));

    $invitations->revokeAllOf($operator->id);

    expect($invitations->byTokenHash(hash('sha256', 'r'))->isUsableAt($now))->toBeFalse();
});
