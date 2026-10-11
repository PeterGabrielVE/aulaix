<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\Institution;

/**
 * The institution a request (or job) is working for. What the rest of the
 * application gets to know about the tenant: never the aggregate itself.
 */
final readonly class TenantContext
{
    public function __construct(
        public InstitutionId $institutionId,
        public string $name,
        public string $subdomain,
    ) {}

    public static function of(Institution $institution): self
    {
        return new self($institution->id, $institution->name, $institution->subdomain->value);
    }
}
