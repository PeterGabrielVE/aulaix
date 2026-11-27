<?php

namespace Tests\Fixtures\Tenancy;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\InstitutionStatusLock;

/**
 * Records shared and exclusive institution status locks for unit tests
 * (SPEC-017 RSP-R08).
 */
final class CountingInstitutionStatusLock implements InstitutionStatusLock
{
    /** @var list<string> */
    public array $events = [];

    public function shared(InstitutionId $id): void
    {
        $this->events[] = "shared:{$id->value}";
    }

    public function exclusive(InstitutionId $id): void
    {
        $this->events[] = "exclusive:{$id->value}";
    }
}
