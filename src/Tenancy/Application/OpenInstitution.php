<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionRepository;
use LogicException;

/**
 * The institution a change applies to: always the open one, never one the
 * request names (INS-R07.1, S-02).
 */
final class OpenInstitution
{
    /**
     * @throws AuthorizationDenied When the person may not manage it (INS-R05.1, NF01).
     */
    public static function forManagement(CurrentTenant $tenant, InstitutionPermissions $permissions): InstitutionId
    {
        if (! $permissions->canManage()) {
            throw new AuthorizationDenied('No tienes permiso para cambiar los datos del plantel.');
        }

        return $tenant->institutionId()
            ?? throw new LogicException('Changing an institution needs an open institution.');
    }

    public static function load(InstitutionRepository $institutions, InstitutionId $id): Institution
    {
        return $institutions->byId($id)
            ?? throw new LogicException("The open institution {$id->value} does not exist.");
    }
}
