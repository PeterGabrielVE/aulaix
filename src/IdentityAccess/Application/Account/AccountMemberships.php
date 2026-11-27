<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Application\Port\InstitutionScope;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\Membership;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Membership\SuspensionReason;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\Tenancy\Application\TenantContext;

/**
 * What happens to a person's memberships in every institution as their
 * account is deleted, each change recorded in that institution's log.
 */
final readonly class AccountMemberships
{
    public function __construct(
        private InstitutionScope $institutions,
        private MembershipRepository $memberships,
        private RoleCatalog $roles,
        private AuditTrail $audit,
    ) {}

    /**
     * IDG-R08.6: the active ones, suspended for the deletion.
     */
    public function suspendForDeletion(AccountId $account): void
    {
        $this->change($account, fn (Membership $membership) => $membership->isActive()
            ? $membership->suspend(SuspensionReason::AccountDeletion)
            : $membership);
    }

    /**
     * IDG-R08.9: only those suspended for the deletion come back.
     */
    public function reactivateAfterCancelledDeletion(AccountId $account): void
    {
        $this->change($account, fn (Membership $membership) => $membership->reactivateIfSuspendedFor(SuspensionReason::AccountDeletion));
    }

    /**
     * IDG-R08.11: all of them end, and their roles go; records stay.
     */
    public function endForClosedAccount(AccountId $account): void
    {
        $this->change($account, function (Membership $membership) use ($account) {
            if ($membership->status !== MembershipStatus::Ended) {
                $this->roles->revokeEverything($account);
            }

            return $membership->end();
        });
    }

    /**
     * @param  callable(Membership): Membership  $transition
     */
    private function change(AccountId $account, callable $transition): void
    {
        $this->institutions->forEachInstitutionOf($account->value, function (TenantContext $institution) use ($account, $transition) {
            $membership = $this->memberships->of($account, $institution->institutionId);

            if ($membership === null) {
                return;
            }

            $changed = $transition($membership);

            if ($changed->status === $membership->status) {
                return;
            }

            $this->memberships->save($changed);

            $this->audit->record(AuditEvent::of(
                match ($changed->status) {
                    MembershipStatus::Active => 'identity.membership.reactivated',
                    MembershipStatus::Suspended => 'identity.membership.suspended',
                    MembershipStatus::Ended => 'identity.membership.ended',
                },
                AuditSubject::of('membership', (int) $membership->id),
                [FieldChange::of('status', $membership->status->value, $changed->status->value)],
            ));
        });
    }
}
