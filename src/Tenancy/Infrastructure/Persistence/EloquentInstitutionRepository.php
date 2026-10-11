<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\Subdomain;

final class EloquentInstitutionRepository implements InstitutionRepository
{
    public function findActiveBySubdomain(Subdomain $subdomain): ?Institution
    {
        $model = InstitutionModel::query()
            ->active()
            ->where('subdomain', $subdomain->value)
            ->first();

        return $model === null ? null : InstitutionMapper::toDomain($model);
    }
}
