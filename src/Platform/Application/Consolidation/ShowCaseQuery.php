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
use AulaX\Shared\Domain\NotFound;

/**
 * One case, found by any of its accounts, for any operator (SPEC-014
 * PLT-R06.1). Looking at it goes to the platform log (PLT-R07.1): it shows
 * names and emails.
 */
final readonly class ShowCaseQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private ConsolidationDesk $desk,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @return array{email: string, accounts: list<array{id: int, name: string, institutions: list<string>}>}
     *
     * @throws AuthorizationDenied
     * @throws NotFound
     */
    public function __invoke(int $accountId): array
    {
        $operator = PlatformAccess::operator($this->session, $this->operators);

        foreach ($this->desk->cases() as $case) {
            if (in_array($accountId, array_column($case['accounts'], 'id'), true)) {
                $this->auditContext->actAsPlatformOperator($operator->id->value);
                $this->transactions->run(fn () => $this->audit->recordForPlatform(PlatformAudit::consolidationCaseViewed($accountId)));

                return $case;
            }
        }

        throw new NotFound('Ese caso no existe o ya se resolvió.');
    }
}
