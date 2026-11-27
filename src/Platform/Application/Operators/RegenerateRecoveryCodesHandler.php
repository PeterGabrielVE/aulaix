<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\RecoveryCodeHasher;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\Clock;

/**
 * An operator replaces its own recovery codes (PLT-R01.10, SPEC-017
 * RSP-R01.6, NF02, NF03): locked under OperatorLock so a racing login with
 * an old code never wins, and guarded by a recent TOTP.
 */
final readonly class RegenerateRecoveryCodesHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorLock $locks,
        private RecoveryCodeHasher $recoveryHasher,
        private OperatorSession $session,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return list<string>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        $operator = PlatformAccess::withRecentTotp($this->session, $this->operators, $this->clock);
        $this->auditContext->actAsPlatformOperator($operator->id->value);
        $plain = AcceptInvitationHandler::newRecoveryCodes();

        $this->transactions->run(function () use ($operator, $plain) {
            $this->locks->lockOperator($operator->id);
            $fresh = $this->operators->byId($operator->id);

            if ($fresh === null || ! $fresh->canLogIn() || $fresh->isBlockedAt($this->clock->now())) {
                throw new AuthorizationDenied('Necesitas una sesión de operador.');
            }

            $this->operators->saveRecoveryCodes($fresh->id, RecoveryCodes::fromPlain($plain, $this->recoveryHasher));
            $this->audit->recordForPlatform(PlatformAudit::recoveryCodesRegenerated($fresh->id));
        });

        return $plain;
    }
}
