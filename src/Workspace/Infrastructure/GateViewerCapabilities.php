<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\IdentityAccess\Application\Members\MembershipContextQuery;
use AulaX\Students\Application\GuardianPortal\IsGuardianQuery;
use AulaX\Workspace\Application\Port\ViewerCapabilities;
use AulaX\Workspace\Application\Viewer;
use AulaX\Workspace\Domain\Capabilities;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Permissions from Laravel's gate, which knows the signed-in person's
 * permissions in the open institution (its permission team); roles from
 * IdentityAccess and the guardian link from Students (A-06). Bound per
 * request: the layout and the home screen ask once.
 */
final class GateViewerCapabilities implements ViewerCapabilities
{
    /**
     * IdentityAccess's Permission cases the menu and the home screen ask
     * about; its domain is not reachable from here (A-06).
     */
    private const array PERMISSIONS = [
        'gestionar-calendario',
        'ver-estructura',
        'ver-estudiantes',
        'gestionar-estudiantes',
        'ver-plantel',
        'gestionar-plantel',
        'gestionar-usuarios',
        'ver-bitacora',
    ];

    /** @var array<int, Viewer> */
    private array $viewers = [];

    public function __construct(
        private readonly Gate $gate,
        private readonly MembershipContextQuery $memberships,
        private readonly IsGuardianQuery $isGuardian,
    ) {}

    public function of(int $accountId): Viewer
    {
        return $this->viewers[$accountId] ??= $this->load($accountId);
    }

    private function load(int $accountId): Viewer
    {
        $membership = ($this->memberships)($accountId);

        return new Viewer(
            new Capabilities(
                array_values(array_filter(self::PERMISSIONS, fn (string $permission) => $this->gate->allows($permission))),
                $membership->roles,
                $membership->primaryRole,
                ($this->isGuardian)($accountId),
            ),
            $membership->activeInstitutions,
        );
    }
}
