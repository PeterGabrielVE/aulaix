<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Policy\LastAdministratorRule;
use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\NotFound;
use AulaX\Tenancy\Application\CurrentTenant;
use LogicException;

/**
 * A person leaves the active institution (IDG-R07.1): the membership ends,
 * its roles go (IDG-R07.2) and the session on this subdomain closes. The
 * account, the other memberships and every record left here stay
 * (IDG-R02.4). The last active Administrador can not leave (IDG-R07.3).
 */
final readonly class LeaveInstitutionHandler
{
    public function __construct(
        private CurrentTenant $tenant,
        private MembershipRepository $memberships,
        private RoleCatalog $roles,
        private Authenticator $authenticator,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $accountId): void
    {
        $institutionId = $this->tenant->institutionId() ?? throw new LogicException('Leaving needs an active institution.');
        $account = AccountId::fromInt($accountId);

        $this->transactions->run(function () use ($account, $institutionId) {
            $membership = $this->memberships->of($account, $institutionId) ?? throw new NotFound('No perteneces a este plantel.');
            $roles = $this->roles->rolesOf($account);

            BusinessRuleViolations::throwIfAny(array_values(array_filter([LastAdministratorRule::check(
                $this->roles->activeHoldersOf(BaseRole::Administrador),
                $membership->isActive() && $roles->has(BaseRole::Administrador),
            )])));

            $this->memberships->save($membership->end());
            $this->roles->assign($account, RoleSet::of());

            $this->audit->record(AuditEvent::of(
                'identity.membership.ended',
                AuditSubject::of('membership', (int) $membership->id),
                [FieldChange::of('roles', $roles->inPriorityOrder(), [])],
            ));
        });

        $this->authenticator->logOut();
    }
}
