<?php

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\Membership;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Membership\SuspensionReason;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;

function activeMembership(): Membership
{
    return Membership::start(AccountId::fromInt(1), InstitutionId::fromInt(1));
}

it('starts active', function () {
    expect(activeMembership()->status)->toBe(MembershipStatus::Active)
        ->and(activeMembership()->isActive())->toBeTrue();
});

it('is suspended with the reason why', function () {
    $membership = activeMembership()->suspend(SuspensionReason::Institution);

    expect($membership->status)->toBe(MembershipStatus::Suspended)
        ->and($membership->suspensionReason)->toBe(SuspensionReason::Institution)
        ->and($membership->isActive())->toBeFalse();
});

it('gets back its access when reactivated', function () {
    $membership = activeMembership()->suspend(SuspensionReason::Institution)->reactivate();

    expect($membership->isActive())->toBeTrue()
        ->and($membership->suspensionReason)->toBeNull();
});

it('reactivates only when it was suspended for the given reason', function (SuspensionReason $suspendedFor, bool $reactivated) {
    $membership = activeMembership()->suspend($suspendedFor)->reactivateIfSuspendedFor(SuspensionReason::AccountDeletion);

    expect($membership->isActive())->toBe($reactivated);
})->with([
    'suspended because the account is being deleted' => [SuspensionReason::AccountDeletion, true],
    'suspended by the institution' => [SuspensionReason::Institution, false],
    'suspended for security' => [SuspensionReason::Security, false],
]);

it('can only suspend an active membership', function () {
    expect(fn () => activeMembership()->suspend(SuspensionReason::Institution)->suspend(SuspensionReason::Security))
        ->toThrow(BusinessRuleViolation::class);
});

it('never comes back once ended', function () {
    $ended = activeMembership()->end();

    expect($ended->status)->toBe(MembershipStatus::Ended)
        ->and(fn () => $ended->reactivate())->toThrow(BusinessRuleViolation::class);
});

it('starts afresh, active, when someone who left is invited and joins again', function () {
    $ended = Membership::reconstitute(4, AccountId::fromInt(7), InstitutionId::fromInt(1), MembershipStatus::Ended, null);

    $rejoined = Membership::rejoin($ended);

    expect($rejoined->id)->toBe(4)
        ->and($rejoined->isActive())->toBeTrue()
        ->and(fn () => Membership::rejoin(Membership::start(AccountId::fromInt(7), InstitutionId::fromInt(1))))->toThrow(LogicException::class);
});
