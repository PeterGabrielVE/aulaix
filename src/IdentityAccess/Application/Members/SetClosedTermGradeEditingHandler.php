<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Policy\RoleAssignmentPolicy;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\NotFound;

/**
 * An Administrador grants or withdraws, to one member of the active
 * institution, the permission to change grades of closed terms (SPEC-007
 * CAL-R05.4). It is the person's, not a role's, and only applies here.
 */
final readonly class SetClosedTermGradeEditingHandler
{
    public function __construct(
        private MemberRules $rules,
        private MembershipRepository $memberships,
        private RoleCatalog $roles,
        private RoleAssignmentPolicy $policy,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws NotFound When the account is not a member of the active institution.
     */
    public function __invoke(int $actorId, int $memberId, bool $allowed): void
    {
        $institution = $this->rules->institution();
        $member = AccountId::fromInt($memberId);

        $this->transactions->run(function () use ($actorId, $member, $allowed, $institution) {
            $membership = $this->memberships->of($member, $institution->institutionId);

            if ($membership === null || $membership->status === MembershipStatus::Ended) {
                throw new NotFound('Esta persona no es miembro del plantel.');
            }

            BusinessRuleViolations::throwIfAny(array_values(array_filter([
                $this->policy->closedTermGradeEditing($this->roles->rolesOf(AccountId::fromInt($actorId))),
            ])));

            if ($this->roles->hasDirectPermission($member, Permission::EditClosedTermGrades) === $allowed) {
                return;
            }

            $allowed
                ? $this->roles->grantDirect($member, Permission::EditClosedTermGrades)
                : $this->roles->revokeDirect($member, Permission::EditClosedTermGrades);

            $this->audit->record(AuditEvent::of(
                $allowed ? 'identity.membership.permission_granted' : 'identity.membership.permission_revoked',
                AuditSubject::of('membership', (int) $membership->id),
                [FieldChange::of('permission', $allowed ? null : Permission::EditClosedTermGrades->value, $allowed ? Permission::EditClosedTermGrades->value : null)],
            ));
        });
    }
}
