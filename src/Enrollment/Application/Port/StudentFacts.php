<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

use AulaX\Students\Application\Enrollable\EnrollableStudent;

/**
 * The students of the open institution, as Students tells them (A-06).
 */
interface StudentFacts
{
    /**
     * @param  list<int>  $ids
     * @return array<int, EnrollableStudent> By id; the ones it does not have are left out.
     */
    public function byIds(array $ids): array;

    /**
     * @return list<EnrollableStudent> The active ones, by family and given names.
     */
    public function active(): array;
}
