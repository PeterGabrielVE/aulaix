<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\Membership;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Membership\SuspensionReason;
use AulaX\IdentityAccess\Domain\Policy\AssignmentRequest;
use AulaX\IdentityAccess\Domain\Policy\LastAdministratorRule;
use AulaX\IdentityAccess\Domain\Policy\RoleAssignmentPolicy;
use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\NotFound;

/**
 * A manager changes a member's roles and suspends or reactivates their
 * membership in the active institution, in one form and one transaction:
 * every broken rule is reported at once (IAM-R10, IDG-R02.2, IDG-R02.3). The
 * account itself (name, email, password) belongs to its owner and is never
 * touched here (IDG-R05.5).
 */
final readonly class UpdateMemberHandler
{
    public function __construct(
        private MemberRules $rules,
        private RoleCatalog $roles,
        private RoleAssignmentPolicy $policy,
        private MembershipRepository $memberships,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws NotFound When the account is not a member of the active institution.
     */
    public function __invoke(UpdateMemberCommand $command): void
    {
        $this->rules->institution();
        $actor = AccountId::fromInt($command->actorId);
        $member = AccountId::fromInt($command->memberId);
        $requested = RoleSet::of(...$command->roles);

        $this->transactions->run(function () use ($command, $actor, $member, $requested) {
            $membership = $this->memberships->ofAccount($member);

            if ($membership === null || $membership->status === MembershipStatus::Ended) {
                throw new NotFound('Esta persona no es miembro del plantel.');
            }

            $current = $this->roles->rolesOf($member);

            $violations = array_values(array_filter([
                $this->rules->unknownRoles($requested),
                ...$this->policy->violations(
                    new AssignmentRequest($this->roles->rolesOf($actor), $current, $requested, $actor->equals($member), $command->active),
                    $this->roles->withPermission(Permission::ManageUsers),
                ),
            ]));

            if ($violations === []) {
                $removesAnActiveAdministrador = $membership->isActive()
                    && $current->has(BaseRole::Administrador)
                    && (! $requested->has(BaseRole::Administrador) || ! $command->active);

                $violations = array_values(array_filter([
                    LastAdministratorRule::check($this->roles->activeHoldersOf(BaseRole::Administrador), $removesAnActiveAdministrador),
                ]));
            }

            BusinessRuleViolations::throwIfAny($violations);

            $this->changeRoles($membership, $current, $requested);
            $this->changeStatus($membership, $command->active);
        });
    }

    private function changeRoles(Membership $membership, RoleSet $current, RoleSet $requested): void
    {
        $before = $current->inPriorityOrder();
        $after = $requested->inPriorityOrder();

        if ($before === $after) {
            return;
        }

        $this->roles->assign($membership->accountId, $requested);

        $this->audit->record(AuditEvent::of(
            'identity.membership.roles_changed',
            AuditSubject::of('membership', (int) $membership->id),
            [FieldChange::of('roles', $before, $after)],
        ));
    }

    private function changeStatus(Membership $membership, bool $active): void
    {
        $changed = match (true) {
            $active => $membership->reactivateIfSuspendedFor(SuspensionReason::Institution),
            $membership->isActive() => $membership->suspend(SuspensionReason::Institution),
            default => $membership,
        };

        if ($changed->status === $membership->status) {
            return;
        }

        $this->memberships->save($changed);

        $this->audit->record(AuditEvent::of(
            $changed->isActive() ? 'identity.membership.reactivated' : 'identity.membership.suspended',
            AuditSubject::of('membership', (int) $membership->id),
            [FieldChange::of('status', $membership->status->value, $changed->status->value)],
        ));
    }
}
