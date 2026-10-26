<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Consolidation;

use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\Port\ConsolidationDesk;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The cases marked for review, for any operator (SPEC-014 PLT-R06.1).
 */
final readonly class ListCasesQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private ConsolidationDesk $desk,
    ) {}

    /**
     * @return list<array{email: string, accounts: list<array{id: int, name: string, institutions: list<string>}>}>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        PlatformAccess::operator($this->session, $this->operators);

        return $this->desk->cases();
    }
}
