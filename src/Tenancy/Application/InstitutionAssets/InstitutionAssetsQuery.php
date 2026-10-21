<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\InstitutionAssets;

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\StoredImage;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\SignatureRepository;

/**
 * The public API for the images of the open institution: the screen and the
 * documents of SPEC-012 (INS-R04.6, R04.7). Who may see a signature is the
 * caller's check; this only ever reads the open institution's files
 * (INS-R04.8).
 */
final readonly class InstitutionAssetsQuery
{
    public function __construct(
        private InstitutionRepository $institutions,
        private SignatureRepository $signatures,
        private CurrentTenant $tenant,
        private InstitutionFiles $files,
    ) {}

    public function __invoke(AssetKind $kind): ?StoredImage
    {
        $path = $this->pathOf($kind);

        return $path === null ? null : $this->files->read($path);
    }

    private function pathOf(AssetKind $kind): ?string
    {
        $role = $kind->signatureRole();

        if ($role !== null) {
            return $this->signatures->of($role)?->filePath;
        }

        $id = $this->tenant->institutionId();

        return $id === null ? null : $this->institutions->byId($id)?->profile->logoPath;
    }
}
