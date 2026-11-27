<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Correction;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;

/**
 * The correction requests of the open institution, for the managers
 * (EST-R08.4).
 */
final readonly class ListCorrectionsQuery
{
    public function __construct(
        private CorrectionList $corrections,
        private StudentPermissions $permissions,
    ) {}

    /**
     * @return list<CorrectionView>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        StudentAccess::ensureCanManage($this->permissions);

        return $this->corrections->all();
    }
}
