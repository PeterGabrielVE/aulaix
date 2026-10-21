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
    /**
     * @param  ?string  $logoVersion  Changes with each logo, so its URL is never stale; null without a logo (INS-R04.6).
     */
    public function __construct(
        public InstitutionId $institutionId,
        public string $name,
        public string $subdomain,
        public ?string $logoVersion = null,
    ) {}

    public static function of(Institution $institution): self
    {
        $logo = $institution->profile->logoPath;

        return new self($institution->id, $institution->name, $institution->subdomain->value, $logo === null ? null : substr(hash('sha256', $logo), 0, 12));
    }
}
