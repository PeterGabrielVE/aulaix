<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Port\InvitationMailer;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\Clock;

/**
 * A manager fixes the name or the email of a pending invitation: the old
 * link stops working and a new one is sent (IDG-R04.7). The only place a
 * manager may still change someone's name or email (IDG-R05.5).
 */
final readonly class CorrectInvitationHandler
{
    public function __construct(
        private MemberRules $rules,
        private InvitationRepository $invitations,
        private InvitationMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function __invoke(CorrectInvitationCommand $command): void
    {
        $institution = $this->rules->institution();
        $name = PersonName::fromString($command->name);
        $email = EmailAddress::fromString($command->email);

        $issued = $this->transactions->run(function () use ($command, $name, $email) {
            $now = $this->clock->now();
            $invitation = $this->rules->pendingInvitation($command->invitationId);

            if (! $email->equals($invitation->email)) {
                BusinessRuleViolations::throwIfAny(array_values(array_filter([
                    $this->rules->claimEmail($email, $now, exceptInvitationId: $invitation->id),
                ])));
            }

            $issued = $invitation->correct($name, $email, $now);
            $this->invitations->save($issued->invitation);

            $this->audit->record(AuditEvent::of('identity.invitation.corrected', AuditSubject::of('invitation', (int) $invitation->id)));

            return $issued;
        });

        $this->mailer->send($issued->invitation, $issued->token, $institution);
    }
}
