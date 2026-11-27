<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * An operator replaces its own recovery codes (PLT-R01.10): the old ones
 * stop working, and the ten new ones are shown once.
 */
final readonly class RegenerateRecoveryCodesHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @return list<string>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        $operator = PlatformAccess::operator($this->session, $this->operators);
        $this->auditContext->actAsPlatformOperator($operator->id->value);
        $plain = AcceptInvitationHandler::newRecoveryCodes();

        $this->transactions->run(function () use ($operator, $plain) {
            $this->operators->saveRecoveryCodes($operator->id, RecoveryCodes::fromPlain($plain));
            $this->audit->recordForPlatform(PlatformAudit::recoveryCodesRegenerated($operator->id));
        });

        return $plain;
    }
}
