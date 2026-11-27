<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Facts;

use AulaX\Documents\Application\DTO\InstitutionHeaderData;
use AulaX\Documents\Application\DTO\SignatureBlockData;
use AulaX\Documents\Application\Port\InstitutionFacts;
use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\StoredImage;
use AulaX\Tenancy\Application\InstitutionAssets\InstitutionAssetsQuery;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionProfileQuery;

/**
 * Reads the open institution's profile, logo and signatures through Tenancy's
 * public Application queries (A-06, INS-R04.6, R04.7, DOC-R05.1 – R05.3).
 */
final readonly class TenancyInstitutionFacts implements InstitutionFacts
{
    public function __construct(
        private InstitutionProfileQuery $profile,
        private InstitutionAssetsQuery $assets,
        private string $appDomain,
        private string $scheme,
    ) {}

    public function header(): InstitutionHeaderData
    {
        $info = ($this->profile)();
        $logo = ($this->assets)(AssetKind::Logo);
        $directorImg = $info->hasDirectorSignature ? ($this->assets)(AssetKind::DirectorSignature) : null;
        $secretaryImg = $info->hasSecretarySignature ? ($this->assets)(AssetKind::SecretarySignature) : null;

        $director = new SignatureBlockData(
            defaultLabel: 'Dirección del Plantel',
            signerName: $info->directorSignerName,
            signerTitle: $info->directorSignerTitle,
            imageDataUri: self::toDataUri($directorImg),
        );

        $secretary = $info->hasSecretarySignature
            ? new SignatureBlockData(
                defaultLabel: 'Secretaría',
                signerName: $info->secretarySignerName,
                signerTitle: $info->secretarySignerTitle,
                imageDataUri: self::toDataUri($secretaryImg),
            )
            : null;

        return new InstitutionHeaderData(
            name: $info->name,
            deaCode: $info->deaCode,
            rif: $info->rif,
            location: $info->location,
            address: $info->address,
            phone: $info->phone,
            email: $info->email,
            logoDataUri: self::toDataUri($logo),
            directorSignature: $director,
            secretarySignature: $secretary,
            missingNotices: $info->missingNotices,
        );
    }

    public function missingNotices(): array
    {
        return ($this->profile)()->missingNotices;
    }

    public function verificationUrlFor(string $code): string
    {
        $subdomain = ($this->profile)()->subdomain;

        return "{$this->scheme}://{$subdomain}.{$this->appDomain}/verificar/{$code}";
    }

    private static function toDataUri(?StoredImage $image): ?string
    {
        return $image === null ? null : 'data:'.$image->mimeType.';base64,'.base64_encode($image->contents);
    }
}
