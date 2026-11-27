<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Institutions;

use AulaX\Platform\Application\Port\InstitutionDirectory;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\Administration\ListAllInstitutionsQuery;
use AulaX\Tenancy\Application\Administration\SetInstitutionStatusHandler;
use AulaX\Tenancy\Application\Administration\ShowInstitutionQuery;

/**
 * The institutions, through Tenancy's Application layer (constitution A-06).
 */
final readonly class TenancyInstitutionDirectory implements InstitutionDirectory
{
    public function __construct(
        private ListAllInstitutionsQuery $list,
        private ShowInstitutionQuery $show,
        private SetInstitutionStatusHandler $setStatus,
    ) {}

    public function page(?string $search, ?string $status, int $page): array
    {
        $result = ($this->list)($search, $status, $page);

        return ['items' => $result->items, 'total' => $result->total, 'page' => $result->page, 'perPage' => $result->perPage];
    }

    public function one(int $id): ?array
    {
        return ($this->show)($id);
    }

    public function setActive(int $id, bool $active): bool
    {
        return ($this->setStatus)(InstitutionId::fromInt($id), $active);
    }
}
