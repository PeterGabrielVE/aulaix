<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\InstitutionId;

/**
 * Institutions are a platform table, without Row Level Security: the
 * central selector reads them with no institution active (SPEC-001). Who
 * may change which one is decided by the use case, which only ever passes
 * the active institution's id (INS-R07.1).
 */
interface InstitutionRepository
{
    /**
     * The institution served at this subdomain, or null when none is active there.
     */
    public function findActiveBySubdomain(Subdomain $subdomain): ?Institution;

    public function byId(InstitutionId $id): ?Institution;

    /**
     * Persists the name, details, type, levels and logo. Never the subdomain
     * nor the status: those are the platform's (INS-R01.6).
     */
    public function save(Institution $institution): void;

    /**
     * Whether another institution than the given one has this DEA code
     * (TEN-R01.2, INS-R01.3).
     */
    public function isDeaCodeTaken(string $deaCode, InstitutionId $except): bool;

    /**
     * Whether another institution than the given one has this RIF
     * (TEN-R01.3, INS-R01.3).
     */
    public function isRifTaken(string $rif, InstitutionId $except): bool;
}
