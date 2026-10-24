<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Domain\SignatureRole;

final class EloquentSignatureRepository implements SignatureRepository
{
    public function of(SignatureRole $role): ?Signature
    {
        $model = SignatureModel::query()->where('role', $role->value)->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function all(): array
    {
        return SignatureModel::query()
            ->get()
            ->map(fn (SignatureModel $model) => self::toDomain($model))
            ->sortBy(fn (Signature $signature) => array_search($signature->role, SignatureRole::cases(), true))
            ->values()
            ->all();
    }

    public function save(Signature $signature): void
    {
        SignatureModel::query()->updateOrCreate(['role' => $signature->role->value], [
            'signer_name' => $signature->signerName,
            'signer_title' => $signature->signerTitle,
            'file_path' => $signature->filePath,
        ]);
    }

    public function delete(SignatureRole $role): void
    {
        SignatureModel::query()->where('role', $role->value)->delete();
    }

    private static function toDomain(SignatureModel $model): Signature
    {
        return Signature::reconstitute(SignatureRole::from($model->role), $model->signer_name, $model->signer_title, $model->file_path);
    }
}
