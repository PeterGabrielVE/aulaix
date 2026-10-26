<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * A new operator, pending until it accepts the link it receives by email
 * (PLT-R02.1, R03.1). From the panel only an active SuperAdmin invites
 * (PLT-R03.8); from the console (no inviter) the server's own user does
 * (V3). Only the token's hash is kept (V4).
 */
final readonly class InviteOperatorHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorInvitationRepository $invitations,
        private OperatorMailer $mailer,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  ?OperatorId  $invitedBy  Null from the console.
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    public function __invoke(string $name, string $email, OperatorRole $role, ?OperatorId $invitedBy): OperatorId
    {
        if ($invitedBy !== null) {
            $inviter = $this->operators->byId($invitedBy);

            if ($inviter === null || ! $inviter->canLogIn() || ! $inviter->role->canManage()) {
                throw new AuthorizationDenied('Solo un Superadministrador puede invitar operadores.');
            }

            $this->auditContext->actAsPlatformOperator($invitedBy->value);
        }

        $address = OperatorEmail::fromString($email);

        if ($this->operators->byEmail($address) !== null) {
            throw BusinessRuleViolation::onField('email', 'Ese correo ya pertenece a un operador.');
        }

        $token = bin2hex(random_bytes(32));

        $id = $this->transactions->run(function () use ($name, $address, $role, $token) {
            $id = $this->operators->save(Operator::invite($name, $address, $role));
            $this->invitations->issue(OperatorInvitation::issue($id, InvitationPurpose::Onboarding, hash('sha256', $token), $this->clock->now()));
            $this->audit->recordForPlatform(PlatformAudit::invited($id, $address->value, $role->value));

            return $id;
        });

        $this->mailer->sendLink($address, trim($name), $token, InvitationPurpose::Onboarding);

        return $id;
    }
}
