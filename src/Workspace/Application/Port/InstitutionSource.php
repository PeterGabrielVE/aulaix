<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\Port;

/**
 * The institution's missing mandatory details, from Tenancy.
 */
interface InstitutionSource
{
    /**
     * @return list<string> levels, dea_code, director_signature, rif, dependency.
     */
    public function gaps(): array;
}
