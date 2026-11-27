<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager withdraws a pending invitation; its link stops working
 * (IDG-R04.10).
 */
final readonly class CancelInvitationHandler
{
    public function __construct(
        private MemberRules $rules,
        private InvitationRepository $invitations,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $invitationId): void
    {
        $this->rules->institution();

        $this->transactions->run(function () use ($invitationId) {
            $this->invitations->save($this->rules->pendingInvitation($invitationId)->cancel());

            $this->audit->record(AuditEvent::of('identity.invitation.cancelled', AuditSubject::of('invitation', $invitationId)));
        });
    }
}
