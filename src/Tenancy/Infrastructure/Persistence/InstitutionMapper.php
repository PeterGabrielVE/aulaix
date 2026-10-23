<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionDependency;
use AulaX\Tenancy\Domain\InstitutionLevels;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionType;
use AulaX\Tenancy\Domain\Subdomain;

final class InstitutionMapper
{
    public static function toDomain(InstitutionModel $model): Institution
    {
        return Institution::reconstitute(
            InstitutionId::fromInt($model->id),
            $model->name,
            Subdomain::fromString($model->subdomain),
            $model->status,
            new InstitutionProfile(
                deaCode: $model->dea_code,
                rif: $model->rif,
                parishId: $model->parish_id,
                address: $model->address,
                phone: $model->phone,
                email: $model->email,
                logoPath: $model->logo_path,
            ),
            $model->type === null ? null : InstitutionType::from($model->type),
            ($model->education_levels ?? []) === [] ? null : InstitutionLevels::of($model->education_levels),
            $model->dependency === null ? null : InstitutionDependency::from($model->dependency),
        );
    }
}
