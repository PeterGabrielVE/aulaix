<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Audit\Application\AuditContext;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\Clock;

/**
 * Asked on every request of the panel. The session stays open only while
 * its operator is still active, with the session version it started with
 * (PLT-R01.4, R03.4, R03.7), and was used in the last 30 minutes
 * (PLT-R01.8). Otherwise it ends. From here on, the log names the operator.
 */
final readonly class CurrentOperatorQuery
{
    private const int IDLE_SECONDS = 30 * 60;

    public function __construct(
        private OperatorSession $session,
        private OperatorRepository $operators,
        private AuditContext $auditContext,
        private Clock $clock,
    ) {}

    public function __invoke(): ?CurrentOperator
    {
        $id = $this->session->current();

        if ($id === null) {
            return null;
        }

        $now = $this->clock->now();
        $operator = $this->operators->byId($id);
        $lastActivity = $this->session->lastActivityAt();

        if ($operator === null
            || ! $operator->canLogIn()
            || $operator->sessionVersion !== $this->session->sessionVersion()
            || $lastActivity === null
            || $now->getTimestamp() - $lastActivity->getTimestamp() > self::IDLE_SECONDS) {
            $this->session->end();

            return null;
        }

        $this->session->touch($now);
        $this->auditContext->actAsPlatformOperator($id->value);

        return new CurrentOperator($id->value, $operator->name, $operator->role->value);
    }
}
