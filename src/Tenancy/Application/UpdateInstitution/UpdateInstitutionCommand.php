<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\UpdateInstitution;

/**
 * The institution form, as typed. Carries no institution id: the open
 * institution is the one that changes (INS-R07.1).
 */
final readonly class UpdateInstitutionCommand
{
    /**
     * @param  list<string>  $levels  Codes of the catalog's education levels.
     */
    public function __construct(
        public string $name,
        public ?string $deaCode,
        public ?string $rif,
        public ?int $parishId,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public string $type,
        public array $levels,
        public ?string $dependency = null,
    ) {}
}
