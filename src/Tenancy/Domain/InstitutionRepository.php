<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

interface InstitutionRepository
{
    /**
     * The institution served at this subdomain, or null when none is active there.
     */
    public function findActiveBySubdomain(Subdomain $subdomain): ?Institution;
}
