<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ListStudents;

use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentDirectory;

/**
 * The students of the open institution for whoever may see them (EST-R05.1 –
 * R05.3): the active ones unless asked for the withdrawn too, paginated.
 */
final readonly class ListStudentsQuery
{
    public const int PER_PAGE = 25;

    public function __construct(
        private StudentDirectory $directory,
        private StudentPermissions $permissions,
    ) {}

    public function __invoke(string $search, bool $includeWithdrawn, int $page): StudentPage
    {
        StudentAccess::ensureCanView($this->permissions);

        return $this->directory->page(trim($search), $includeWithdrawn, max(1, $page), self::PER_PAGE);
    }
}
