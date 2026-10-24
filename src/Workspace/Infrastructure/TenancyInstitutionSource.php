<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\Tenancy\Application\InstitutionGaps\InstitutionGapsQuery;
use AulaX\Workspace\Application\Port\InstitutionSource;

/**
 * Through Tenancy's public query (A-06), which checks gestionar-plantel.
 */
final readonly class TenancyInstitutionSource implements InstitutionSource
{
    public function __construct(private InstitutionGapsQuery $gaps) {}

    public function gaps(): array
    {
        return ($this->gaps)();
    }
}
