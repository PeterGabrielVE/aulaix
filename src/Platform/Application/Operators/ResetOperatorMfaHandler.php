<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\NotFound;

/**
 * Another operator lost its authenticator (PLT-R03.7): its TOTP and its
 * recovery codes are voided, its sessions end, its pending links are
 * revoked, any active lockout is lifted (SPEC-017 RSP-R03.11), and a new
 * one-use link by email is its only way back in (V4).
 */
final readonly class ResetOperatorMfaHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorInvitationRepository $invitations,
        private OperatorLock $locks,
        private OperatorSession $session,
        private OperatorMailer $mailer,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws NotFound
     */
    public function __invoke(OperatorId $target): void
    {
        $actor = PlatformAccess::superAdminWithRecentTotp($this->session, $this->operators, $this->clock);
        $this->auditContext->actAsPlatformOperator($actor->id->value);

        if ($actor->id->equals($target)) {
            throw BusinessRuleViolation::onField('operator', 'Otro Superadministrador debe restablecer tu segundo factor.');
        }

        $token = bin2hex(random_bytes(32));

        /** @var Operator $operator */
        $operator = $this->transactions->run(function () use ($target, $token) {
            $this->locks->lockOperator($target);
            PlatformAccess::superAdminWithRecentTotp($this->session, $this->operators, $this->clock);
            $operator = $this->operators->byId($target) ?? throw new NotFound('Ese operador no existe.');

            $operator->resetMfa();
            $this->operators->save($operator);
            $this->operators->saveRecoveryCodes($operator->id, RecoveryCodes::reconstitute([]));
            $this->invitations->issue(OperatorInvitation::issue($operator->id, InvitationPurpose::MfaReset, hash('sha256', $token), $this->clock->now()));
            $this->audit->recordForPlatform(PlatformAudit::mfaReset($operator->id));

            return $operator;
        });

        $this->mailer->sendLink($operator->email, $operator->name, $token, InvitationPurpose::MfaReset);
    }
}
