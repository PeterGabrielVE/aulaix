<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Application\Port\InvitationMailer;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\IdentityAccess\Domain\Invitation\Invitation;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\IdentityAccess\Domain\Invitation\IssuedInvitation;
use AulaX\IdentityAccess\Domain\Policy\AssignmentRequest;
use AulaX\IdentityAccess\Domain\Policy\RoleAssignmentPolicy;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\Clock;

/**
 * A manager invites an email to the active institution with some roles
 * (IDG-R04.1). The answer is the same whether the email has an account or
 * not (IDG-R04.2): the account, if any, is only looked at when the
 * invitation is accepted.
 */
final readonly class InviteMemberHandler
{
    public function __construct(
        private MemberRules $rules,
        private RoleCatalog $roles,
        private RoleAssignmentPolicy $policy,
        private InvitationRepository $invitations,
        private InvitationMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function __invoke(InviteMemberCommand $command): void
    {
        $institution = $this->rules->institution();
        $actor = AccountId::fromInt($command->actorId);
        $email = EmailAddress::fromString($command->email);
        $name = PersonName::fromString($command->name);
        $roles = RoleSet::of(...$command->roles);

        $issued = $this->transactions->run(function () use ($institution, $actor, $email, $name, $roles) {
            $now = $this->clock->now();

            BusinessRuleViolations::throwIfAny(array_values(array_filter([
                $this->rules->unknownRoles($roles),
                ...$this->policy->violations(
                    new AssignmentRequest($this->roles->rolesOf($actor), null, $roles, editingSelf: false),
                    $this->roles->withPermission(Permission::ManageUsers),
                ),
                $this->rules->claimEmail($email, $now),
            ])));

            $issued = Invitation::issue($institution->institutionId, $email, $name, $roles, $actor, $now);
            $invitation = $this->invitations->save($issued->invitation);

            // Only ids and roles: never the email, the name or the token (IDG-R10.2).
            $this->audit->record(AuditEvent::of(
                'identity.invitation.created',
                AuditSubject::of('invitation', (int) $invitation->id),
                [FieldChange::of('roles', [], $roles->inPriorityOrder())],
            ));

            return new IssuedInvitation($invitation, $issued->token);
        });

        $this->mailer->send($issued->invitation, $issued->token, $institution);
    }
}
