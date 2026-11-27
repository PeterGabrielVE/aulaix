<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Consolidation;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\ConsolidationDesk;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\Clock;

/**
 * The cases marked for review, for any operator (SPEC-014 PLT-R06.1).
 * Recorded in the platform log with only the displayed account IDs
 * (SPEC-017 RSP-R07.1, R07.3).
 */
final readonly class ListCasesQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private ConsolidationDesk $desk,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return list<array{email: string, accounts: list<array{id: int, name: string, institutions: list<string>}>}>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        $operator = PlatformAccess::operator($this->session, $this->operators, $this->clock->now());
        $cases = $this->desk->cases();

        $accountIds = [];

        foreach ($cases as $case) {
            foreach ($case['accounts'] as $account) {
                $accountIds[] = (int) $account['id'];
            }
        }

        $this->auditContext->actAsPlatformOperator($operator->id->value);
        $this->transactions->run(fn () => $this->audit->recordForPlatform(PlatformAudit::consolidationCasesListed($accountIds)));

        return $cases;
    }
}
