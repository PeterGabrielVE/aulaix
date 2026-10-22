<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Application\Events\MemberJoined;
use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\InvalidEmailAddress;
use AulaX\IdentityAccess\Domain\Invitation\InvitationNotUsable;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\IdentityAccess\Domain\Membership\Membership;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\Shared\Application\EventBus;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Clock;
use AulaX\Tenancy\Application\ResolveTenant\InstitutionNotFound;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantHandler;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantQuery;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;

/**
 * A person without an account accepts an invitation from its emailed link,
 * on the central domain: the account is created with the invitation's name,
 * the chosen password and the email verified by the link itself, together
 * with the membership and its roles (IDG-R04.4). A used, expired, cancelled
 * or wrong link is refused, always with the same answer (IDG-R04.9).
 *
 * The institution is active only while its invitation is read and the
 * membership is created.
 */
final readonly class AcceptInvitationHandler
{
    public function __construct(
        private ResolveTenantHandler $resolveTenant,
        private TenantActivator $tenantActivator,
        private AccountRepository $accounts,
        private MembershipRepository $memberships,
        private InvitationRepository $invitations,
        private RoleCatalog $roles,
        private PasswordHasher $hasher,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
        private EventBus $events,
    ) {}

    /**
     * @return TenantContext The institution just joined.
     *
     * @throws InvitationNotUsable
     * @throws AccountAlreadyExists
     */
    public function __invoke(AcceptInvitationCommand $command): TenantContext
    {
        try {
            $institution = ($this->resolveTenant)(new ResolveTenantQuery($command->subdomain));
            $email = EmailAddress::fromString($command->email);
        } catch (InstitutionNotFound|InvalidEmailAddress) {
            throw InvitationNotUsable::create();
        }

        // Read before the institution is active: once it is, Row Level
        // Security hides every account that is not yet a member of it.
        $hasAccount = $this->accounts->byEmail($email) !== [];

        $this->tenantActivator->activate($institution);

        try {
            $this->transactions->run(function () use ($command, $institution, $email, $hasAccount) {
                $now = $this->clock->now();
                $invitation = $this->invitations->pendingFor($institution->institutionId, $email);

                if ($invitation === null || ! $invitation->isUsable($now) || ! $invitation->matches($command->token)) {
                    throw InvitationNotUsable::create();
                }

                if ($hasAccount) {
                    throw AccountAlreadyExists::create();
                }

                $account = $this->accounts->save(Account::register($email, $invitation->name, $this->hasher->hash($command->password), $now));
                $membership = $this->memberships->save(Membership::start($account->id, $institution->institutionId));
                $this->roles->assign($account->id, $invitation->roles);
                $this->invitations->save($invitation->accept($command->token, $account->id, $now));

                $this->audit->record(AuditEvent::of('identity.invitation.accepted', AuditSubject::of('invitation', (int) $invitation->id)));
                $this->audit->record(AuditEvent::of(
                    'identity.membership.created',
                    AuditSubject::of('membership', (int) $membership->id),
                    [FieldChange::of('roles', [], $invitation->roles->inPriorityOrder())],
                ));
                $this->events->publish(new MemberJoined($institution->institutionId->value, $account->id->value, (int) $invitation->id, $now));
            });
        } finally {
            $this->tenantActivator->deactivate();
        }

        return $institution;
    }
}
