<?php

namespace App\Providers;

use AulaX\Tenancy\Application\TenantActivationHook;
use AulaX\Tenancy\Application\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/**
 * Scopes spatie/laravel-permission's roles and permissions to the active
 * institution, its "team" (F1-06). Lives here until the IdentityAccess module
 * exists (SPEC-004), then moves to its infrastructure.
 */
class SpatiePermissionTeamHook implements TenantActivationHook
{
    public function __construct(private readonly PermissionRegistrar $permissionRegistrar) {}

    public function onActivated(TenantContext $context): void
    {
        $this->permissionRegistrar->setPermissionsTeamId($context->institutionId->value);
    }

    public function onDeactivated(): void
    {
        $this->permissionRegistrar->setPermissionsTeamId(null);
    }
}
