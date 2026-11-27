<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Shared\Application\TransactionManager;

/**
 * PLT-R01.9: the session ends on the server, and the log says so.
 */
final readonly class LogOutOperatorHandler
{
    public function __construct(
        private OperatorSession $session,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(): void
    {
        $operator = $this->session->current();

        if ($operator !== null) {
            $this->auditContext->actAsPlatformOperator($operator->value);
            $this->transactions->run(fn () => $this->audit->recordForPlatform(PlatformAudit::logout($operator)));
        }

        $this->session->end();
    }
}
