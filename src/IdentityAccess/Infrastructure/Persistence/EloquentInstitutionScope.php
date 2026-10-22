<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Application\Port\InstitutionScope;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The account's institutions are read with no institution active (ADR 0006
 * C); each one is then activated by id, so inactive institutions are
 * included too.
 */
final readonly class EloquentInstitutionScope implements InstitutionScope
{
    public function __construct(private TenantActivator $tenantActivator) {}

    public function forEachInstitutionOf(int $accountId, callable $operation): void
    {
        $institutions = DB::table('institution_memberships as membership')
            ->join('institutions', 'institutions.id', '=', 'membership.institution_id')
            ->where('membership.user_id', $accountId)
            ->orderBy('institutions.id')
            ->get(['institutions.id', 'institutions.name', 'institutions.subdomain']);

        try {
            foreach ($institutions as $institution) {
                $context = new TenantContext(InstitutionId::fromInt((int) $institution->id), $institution->name, $institution->subdomain);
                $this->tenantActivator->activate($context);
                $operation($context);
            }
        } finally {
            $this->tenantActivator->deactivate();
        }
    }
}
