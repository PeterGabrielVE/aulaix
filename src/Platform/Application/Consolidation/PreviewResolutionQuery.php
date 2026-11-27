<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Consolidation;

use AulaX\Audit\Application\AuditContext;
use AulaX\Platform\Application\Port\ConsolidationDesk;
use AulaX\Platform\Application\Port\ConsolidationPreviews;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * What a resolution would change, without changing it (SPEC-014 PLT-R06.3,
 * IDG-R11.4), remembered in the operator's session so ResolveCaseHandler can
 * enforce it on the server (SPEC-017 RSP-R05.1, R05.4).
 */
final readonly class PreviewResolutionQuery extends CaseResolver
{
    public function __construct(
        OperatorRepository $operators,
        OperatorSession $session,
        ConsolidationDesk $desk,
        AuditContext $auditContext,
        Clock $clock,
        private ConsolidationPreviews $previews,
    ) {
        parent::__construct($operators, $session, $desk, $auditContext, $clock);
    }

    /**
     * @param  list<int>  $accountIds
     * @return array{decision: string, survivorId: ?int, affectedIds: list<int>, applied: bool}
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    public function __invoke(int $caseAccountId, array $accountIds, string $decision, string $reason, ?string $verificationRef): array
    {
        $this->assertCaseAccount($caseAccountId, $accountIds);
        $plan = $this->resolve($accountIds, $decision, $reason, $verificationRef, dryRun: true);
        $this->previews->remember($accountIds, $decision, $this->clock->now());

        return $plan;
    }
}
