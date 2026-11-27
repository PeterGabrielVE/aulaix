<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\InstitutionRepository;

/**
 * The tenant context of an institution by its id, for the few places that
 * act on an institution other than the request's (SPEC-009 transfers, ADR
 * 0008, SPEC-017 RSP-R08).
 */
final readonly class InstitutionContexts
{
    public function __construct(
        private InstitutionRepository $institutions,
        private InstitutionStatusLock $locks,
    ) {}

    /**
     * Read-only lookup: returns active and inactive institutions alike, used
     * by platform aggregate reads (RSP-R08.6, PLT-R04.3).
     */
    public function of(InstitutionId $id): ?TenantContext
    {
        $institution = $this->institutions->byId($id);

        return $institution === null ? null : TenantContext::of($institution);
    }

    /**
     * Takes the shared status lock, re-reads the institution and refuses
     * inactive or missing ones before any cross-tenant or queued write
     * (ADR 0014, RSP-R08.1, R08.5).
     *
     * @throws InstitutionUnavailable
     */
    public function writableOf(InstitutionId $id): TenantContext
    {
        $this->locks->shared($id);
        $institution = $this->institutions->byId($id);

        if ($institution === null || ! $institution->isActive()) {
            throw InstitutionUnavailable::forId($id);
        }

        return TenantContext::of($institution);
    }
}
