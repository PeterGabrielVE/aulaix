<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\InstitutionSettings;

/**
 * Public institution header and signature metadata for SPEC-012 document
 * rendering (INS-R04.7, DOC-R05.1 – R05.3).
 */
final readonly class InstitutionProfileView
{
    /**
     * @param  list<string>  $missingNotices
     */
    public function __construct(
        public string $name,
        public string $subdomain,
        public ?string $deaCode,
        public ?string $rif,
        public ?string $location,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public ?string $directorSignerName,
        public ?string $directorSignerTitle,
        public bool $hasDirectorSignature,
        public ?string $secretarySignerName,
        public ?string $secretarySignerTitle,
        public bool $hasSecretarySignature,
        public array $missingNotices,
    ) {}
}
