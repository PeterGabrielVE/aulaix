<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Shared\Domain\InstitutionId;
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

    public function byId(InstitutionId $id): ?Institution
    {
        $model = InstitutionModel::query()->find($id->value);

        return $model === null ? null : InstitutionMapper::toDomain($model);
    }

    public function save(Institution $institution): void
    {
        // Only what the institution maintains itself: never the subdomain
        // nor the status (INS-R01.6).
        InstitutionModel::query()->whereKey($institution->id->value)->update([
            'name' => $institution->name,
            'dea_code' => $institution->profile->deaCode,
            'rif' => $institution->profile->rif,
            'parish_id' => $institution->profile->parishId,
            'address' => $institution->profile->address,
            'phone' => $institution->profile->phone,
            'email' => $institution->profile->email,
            'logo_path' => $institution->profile->logoPath,
            'type' => $institution->type?->value,
            'dependency' => $institution->dependency?->value,
            'education_levels' => json_encode($institution->levels?->codes() ?? []),
            'updated_at' => now(),
        ]);
    }

    public function isDeaCodeTaken(string $deaCode, InstitutionId $except): bool
    {
        return InstitutionModel::query()->where('dea_code', $deaCode)->whereKeyNot($except->value)->exists();
    }

    public function isRifTaken(string $rif, InstitutionId $except): bool
    {
        return InstitutionModel::query()->where('rif', $rif)->whereKeyNot($except->value)->exists();
    }
}
