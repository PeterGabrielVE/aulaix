<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Policy\AssignmentRequest;
use AulaX\IdentityAccess\Domain\Policy\RoleAssignmentPolicy;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\NotFound;

/**
 * Another module gives an active member of the open institution one more
 * role (SPEC-009 EST-R08.7: linking a guardian makes them Representante),
 * under the rules of the users screen (IAM-R10) and with the same audit
 * entry. Granting a role the member already has changes nothing.
 */
final readonly class GrantRoleHandler
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
     * @throws NotFound When the account is not an active member of the open institution.
     * @throws BusinessRuleViolations
     */
    public function __invoke(int $actorId, int $memberId, string $role): void
    {
        $institution = $this->rules->institution();
        $actor = AccountId::fromInt($actorId);
        $member = AccountId::fromInt($memberId);

        $this->transactions->run(function () use ($institution, $actor, $member, $role) {
            $membership = $this->memberships->of($member, $institution->institutionId);

            if ($membership === null || ! $membership->isActive()) {
                throw new NotFound('Esta persona no es miembro del plantel.');
            }

            $current = $this->roles->rolesOf($member);

            if ($current->has($role)) {
                return;
            }

            $requested = RoleSet::of(...[...$current->names(), $role]);

            BusinessRuleViolations::throwIfAny(array_values(array_filter([
                $this->rules->unknownRoles($requested),
                ...$this->policy->violations(
                    new AssignmentRequest($this->roles->rolesOf($actor), $current, $requested, $actor->equals($member)),
                    $this->roles->withPermission(Permission::ManageUsers),
                ),
            ])));

            $this->roles->assign($member, $requested);

            $this->audit->record(AuditEvent::of(
                'identity.membership.roles_changed',
                AuditSubject::of('membership', (int) $membership->id),
                [FieldChange::of('roles', $current->inPriorityOrder(), $requested->inPriorityOrder())],
            ));
        });
    }
}
