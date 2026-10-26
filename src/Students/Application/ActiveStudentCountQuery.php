<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Shared\Application\PlatformReadScope;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * For the platform (SPEC-014 PLT-R04.3, V2): how many active students the
 * open institution has. A number only, and only inside the platform's
 * bounded read.
 */
final readonly class ActiveStudentCountQuery
{
    public function __construct(private StudentDirectory $students) {}

    /**
     * @throws AuthorizationDenied
     */
    public function __invoke(): int
    {
        PlatformReadScope::ensureActive();

        return $this->students->page('', includeWithdrawn: false, page: 1, perPage: 1)->total;
    }
}
