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
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\NotFound;
use Closure;

/**
 * Grants or withdraws a permission to one member of the active institution,
 * person by person and not through a role (CAL-R05.4, SPEC-010 MAT-R11.3),
 * recorded in the audit log. Who may do it is the caller's policy.
 */
final readonly class DirectPermissionGrant
{
    public function __construct(
        private MemberRules $rules,
        private MembershipRepository $memberships,
        private RoleCatalog $roles,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  Closure(RoleSet): ?BusinessRuleViolation  $mayGrant  The policy of the actor's roles.
     *
     * @throws NotFound When the account is not a member of the active institution.
     * @throws BusinessRuleViolations When the actor may not grant it.
     */
    public function __invoke(int $actorId, int $memberId, Permission $permission, bool $allowed, Closure $mayGrant): void
    {
        $institution = $this->rules->institution();
        $member = AccountId::fromInt($memberId);

        $this->transactions->run(function () use ($actorId, $member, $permission, $allowed, $mayGrant, $institution) {
            $membership = $this->memberships->of($member, $institution->institutionId);

            if ($membership === null || $membership->status === MembershipStatus::Ended) {
                throw new NotFound('Esta persona no es miembro del plantel.');
            }

            BusinessRuleViolations::throwIfAny(array_values(array_filter([
                $mayGrant($this->roles->rolesOf(AccountId::fromInt($actorId))),
            ])));

            if ($this->roles->hasDirectPermission($member, $permission) === $allowed) {
                return;
            }

            $allowed
                ? $this->roles->grantDirect($member, $permission)
                : $this->roles->revokeDirect($member, $permission);

            $this->audit->record(AuditEvent::of(
                $allowed ? 'identity.membership.permission_granted' : 'identity.membership.permission_revoked',
                AuditSubject::of('membership', (int) $membership->id),
                [FieldChange::of('permission', $allowed ? null : $permission->value, $allowed ? $permission->value : null)],
            ));
        });
    }
}
