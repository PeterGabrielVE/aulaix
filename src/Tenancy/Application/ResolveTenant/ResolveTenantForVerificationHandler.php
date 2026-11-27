<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ResolveTenant;

use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InvalidSubdomain;
use AulaX\Tenancy\Domain\Subdomain;

/**
 * Resolves an institution for the public read-only QR verification route
 * (SPEC-012 DOC-R07.5, ADR 0015), accepting both active and inactive
 * institutions without revealing their commercial status.
 *
 * Confined by LayersTest exclusively to ResolveTenantForVerification.
 */
final readonly class ResolveTenantForVerificationHandler
{
    public function __construct(private InstitutionRepository $institutions) {}

    /**
     * @throws InstitutionNotFound
     */
    public function __invoke(ResolveTenantQuery $query): TenantContext
    {
        try {
            $subdomain = Subdomain::fromString($query->subdomain);
        } catch (InvalidSubdomain) {
            throw InstitutionNotFound::atSubdomain();
        }

        $institution = $this->institutions->findBySubdomain($subdomain)
            ?? throw InstitutionNotFound::atSubdomain();

        return TenantContext::of($institution);
    }
}
