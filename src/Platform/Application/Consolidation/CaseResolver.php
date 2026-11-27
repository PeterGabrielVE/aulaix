<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Consolidation;

use AulaX\Audit\Application\AuditContext;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\Port\ConsolidationDesk;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * What previewing and resolving share (SPEC-014 PLT-R06.2 – R06.5): the
 * operator of the session, named as the actor of the log and checked by
 * IdentityAccess like any other (IDG-NF07), and a decision to apply.
 */
abstract readonly class CaseResolver
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private ConsolidationDesk $desk,
        private AuditContext $auditContext,
    ) {}

    /**
     * @param  list<int>  $accountIds
     * @return array{decision: string, survivorId: ?int, affectedIds: list<int>, applied: bool}
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    protected function resolve(array $accountIds, string $decision, string $reason, ?string $verificationRef, bool $dryRun): array
    {
        $operator = PlatformAccess::operator($this->session, $this->operators);

        if (! in_array($decision, ['consolidate', 'separate'], true)) {
            throw BusinessRuleViolation::onField('decision', 'Elige consolidar o mantener separadas.');
        }

        $this->auditContext->actAsPlatformOperator($operator->id->value);

        return $this->desk->resolve($operator->email->value, $accountIds, $decision, $reason, $verificationRef, $dryRun);
    }
}
