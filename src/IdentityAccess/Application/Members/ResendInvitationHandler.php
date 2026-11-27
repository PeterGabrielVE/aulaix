<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Port\InvitationMailer;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Clock;

/**
 * A manager sends a pending invitation again, with a new link valid for
 * another seven days; the previous link stops working (IDG-R04.8).
 */
final readonly class ResendInvitationHandler
{
    public function __construct(
        private MemberRules $rules,
        private InvitationRepository $invitations,
        private InvitationMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function __invoke(int $invitationId): void
    {
        $institution = $this->rules->institution();

        $issued = $this->transactions->run(function () use ($invitationId) {
            $invitation = $this->rules->pendingInvitation($invitationId);

            $issued = $invitation->resend($this->clock->now());
            $this->invitations->save($issued->invitation);

            $this->audit->record(AuditEvent::of('identity.invitation.resent', AuditSubject::of('invitation', $invitationId)));

            return $issued;
        });

        $this->mailer->send($issued->invitation, $issued->token, $institution);
    }
}
