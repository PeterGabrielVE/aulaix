<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Application\Events\MemberJoined;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\InvalidEmailAddress;
use AulaX\IdentityAccess\Domain\Invitation\InvitationNotUsable;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\IdentityAccess\Domain\Membership\Membership;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\Shared\Application\EventBus;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\NotFound;
use AulaX\Tenancy\Application\ResolveTenant\InstitutionNotFound;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantHandler;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantQuery;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;

/**
 * A person who already has an account accepts an invitation, logged in: only
 * the membership is created, with the invitation's roles; their name and
 * password stay as they are (IDG-R04.5). Logging in with the invited email
 * proves they receive mail there, so no token is needed; an account with
 * another email is refused (IDG-R04.6).
 */
final readonly class JoinInstitutionHandler
{
    public function __construct(
        private ResolveTenantHandler $resolveTenant,
        private TenantActivator $tenantActivator,
        private AccountRepository $accounts,
        private MembershipRepository $memberships,
        private InvitationRepository $invitations,
        private RoleCatalog $roles,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
        private EventBus $events,
    ) {}

    /**
     * @throws InvitationNotUsable
     * @throws InvitationEmailMismatch
     */
    public function __invoke(JoinInstitutionCommand $command): TenantContext
    {
        $account = $this->accounts->byId(AccountId::fromInt($command->accountId)) ?? throw new NotFound('La cuenta no existe.');

        try {
            $institution = ($this->resolveTenant)(new ResolveTenantQuery($command->subdomain));
            $invitedEmail = $command->invitedEmail === null ? $account->email : EmailAddress::fromString($command->invitedEmail);
        } catch (InstitutionNotFound|InvalidEmailAddress) {
            throw InvitationNotUsable::create();
        }

        // IDG-R08.8: not while the account waits to be deleted.
        if (! $account->isActive()) {
            throw BusinessRuleViolation::onField('email', 'Tu cuenta está en proceso de eliminación: cancélala para aceptar invitaciones.');
        }

        if (! $invitedEmail->equals($account->email)) {
            throw InvitationEmailMismatch::create();
        }

        $this->tenantActivator->activate($institution);

        try {
            $this->transactions->run(function () use ($account, $institution) {
                $now = $this->clock->now();
                $invitation = $this->invitations->pendingFor($institution->institutionId, $account->email);

                if ($invitation === null) {
                    throw InvitationNotUsable::create();
                }

                $previous = $this->memberships->of($account->id, $institution->institutionId);

                if ($previous !== null && $previous->status !== MembershipStatus::Ended) {
                    throw BusinessRuleViolation::onField('email', 'Ya perteneces a este plantel.');
                }

                // Someone who left comes back through a new invitation only.
                $membership = $this->memberships->save($previous === null
                    ? Membership::start($account->id, $institution->institutionId)
                    : Membership::rejoin($previous));
                $this->roles->assign($account->id, $invitation->roles);
                $this->invitations->save($invitation->acceptBy($account->id, $now));

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
