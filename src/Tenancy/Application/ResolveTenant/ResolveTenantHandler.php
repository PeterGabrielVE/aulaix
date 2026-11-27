<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ResolveTenant;

use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InvalidSubdomain;
use AulaX\Tenancy\Domain\Subdomain;

/**
 * Which institution a subdomain serves (TEN-R03.1 – TEN-R03.4). Only active
 * institutions resolve. A subdomain that is not even valid is reported as
 * not found too: to whoever typed the address it simply does not exist.
 */
final readonly class ResolveTenantHandler
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

        $institution = $this->institutions->findActiveBySubdomain($subdomain)
            ?? throw InstitutionNotFound::atSubdomain();

        return TenantContext::of($institution);
    }
}
