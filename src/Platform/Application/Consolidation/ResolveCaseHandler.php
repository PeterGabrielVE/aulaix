<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Consolidation;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\Access\RecentTotpQuery;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\ConsolidationDesk;
use AulaX\Platform\Application\Port\ConsolidationPreviews;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * Applies a resolution, all or nothing, with the same rules as the console
 * command (SPEC-014 PLT-R06.4, R06.5, IDG-NF07), after enforcing a recent TOTP
 * (RSP-NF03), case account match (RSP-R05.4), and a matching preview from the
 * last 10 minutes (RSP-R05.1 – R05.3).
 */
final readonly class ResolveCaseHandler extends CaseResolver
{
    public function __construct(
        OperatorRepository $operators,
        OperatorSession $session,
        ConsolidationDesk $desk,
        AuditContext $auditContext,
        Clock $clock,
        private ConsolidationPreviews $previews,
        private AuditTrail $audit,
        private TransactionManager $transactions,
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
        $operator = PlatformAccess::withRecentTotp($this->session, $this->operators, $this->clock);
        $this->assertCaseAccount($caseAccountId, $accountIds);

        if (! $this->previews->matches($accountIds, $decision, $this->clock->now(), RecentTotpQuery::WINDOW_SECONDS)) {
            $this->auditContext->actAsPlatformOperator($operator->id->value);
            $this->transactions->run(fn () => $this->audit->recordForPlatform(
                PlatformAudit::consolidationResolveRefused($accountIds, 'no_preview'),
            ));

            throw BusinessRuleViolation::onField('preview', 'Revisa la vista previa antes de resolver.');
        }

        $resolution = $this->resolve($accountIds, $decision, $reason, $verificationRef, dryRun: false);
        $this->previews->forget();

        return $resolution;
    }
}
