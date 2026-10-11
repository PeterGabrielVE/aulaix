<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;

/**
 * An educational institution (plantel): the tenant every institution-scoped
 * record belongs to. Phase 1 has no use case that creates or edits one (they
 * are seeded), so the aggregate is only reconstituted from storage for now.
 */
final readonly class Institution
{
    private function __construct(
        public InstitutionId $id,
        public string $name,
        public Subdomain $subdomain,
        public InstitutionStatus $status,
        public InstitutionProfile $profile,
    ) {}

    public static function reconstitute(
        InstitutionId $id,
        string $name,
        Subdomain $subdomain,
        InstitutionStatus $status,
        InstitutionProfile $profile,
    ): self {
        if (trim($name) === '') {
            throw BusinessRuleViolation::onField('name', 'El nombre del plantel es obligatorio.');
        }

        return new self($id, $name, $subdomain, $status, $profile);
    }

    public function isActive(): bool
    {
        return $this->status === InstitutionStatus::Active;
    }
}
