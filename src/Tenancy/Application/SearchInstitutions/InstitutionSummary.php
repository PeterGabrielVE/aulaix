<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\SearchInstitutions;

/**
 * What the public selector may know about an institution: just enough to
 * send the visitor to its subdomain.
 */
final readonly class InstitutionSummary
{
    public function __construct(
        public string $name,
        public string $subdomain,
    ) {}
}
