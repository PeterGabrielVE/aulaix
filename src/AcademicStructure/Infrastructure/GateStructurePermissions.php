<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Infrastructure;

use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Asks Laravel's gate, which knows the permissions of the signed-in person
 * in the open institution (its permission team).
 */
final readonly class GateStructurePermissions implements StructurePermissions
{
    /**
     * IdentityAccess's Permission cases; its domain is not reachable from
     * here (A-06).
     */
    private const string MANAGE_STRUCTURE = 'gestionar-estructura';

    private const string VIEW_STRUCTURE = 'ver-estructura';

    public function __construct(private Gate $gate) {}

    public function canManage(): bool
    {
        return $this->gate->allows(self::MANAGE_STRUCTURE);
    }

    public function canView(): bool
    {
        return $this->gate->allows(self::VIEW_STRUCTURE);
    }
}
