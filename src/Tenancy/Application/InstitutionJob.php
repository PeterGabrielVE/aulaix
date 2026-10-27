<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

/**
 * A queued job that acts on one institution (SPEC-015 RLT-R03.2): it
 * carries the institution it was queued for, and runs in it through the
 * RunInInstitution job middleware, never in whatever institution the
 * worker last served.
 */
interface InstitutionJob
{
    public function institutionId(): int;
}
