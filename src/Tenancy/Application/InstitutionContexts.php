<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\InstitutionRepository;

/**
 * The tenant context of an institution by its id, for the few places that
 * act on an institution other than the request's (SPEC-009 transfers, ADR
 * 0008).
 */
final readonly class InstitutionContexts
{
    public function __construct(private InstitutionRepository $institutions) {}

    public function of(InstitutionId $id): ?TenantContext
    {
        $institution = $this->institutions->byId($id);

        return $institution === null ? null : TenantContext::of($institution);
    }
}
