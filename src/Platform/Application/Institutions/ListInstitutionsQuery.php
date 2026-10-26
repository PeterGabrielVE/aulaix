<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Institutions;

use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\Port\InstitutionDirectory;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * Every institution, for any operator (SPEC-014 PLT-R04.1, R04.2).
 */
final readonly class ListInstitutionsQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private InstitutionDirectory $institutions,
    ) {}

    /**
     * @return array{items: list<array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}>, total: int, page: int, perPage: int}
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(?string $search, ?string $status, int $page): array
    {
        PlatformAccess::operator($this->session, $this->operators);

        return $this->institutions->page($search, in_array($status, ['active', 'inactive'], true) ? $status : null, $page);
    }
}
