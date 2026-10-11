<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

/**
 * The official details of an institution, all optional (TEN-R01.4). DEA code
 * and RIF are unique across institutions: a rule between aggregates, enforced
 * by unique indexes. The parish is referenced by id only, since Tenancy does
 * not import the Catalogs domain (A-06).
 */
final readonly class InstitutionProfile
{
    public function __construct(
        public ?string $deaCode = null,
        public ?string $rif = null,
        public ?int $parishId = null,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $logoPath = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
