<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\Port;

use AulaX\Workspace\Application\Viewer;

/**
 * What the signed-in person can do in the open institution: their
 * permissions, roles and guardian link, from the modules that own them.
 */
interface ViewerCapabilities
{
    public function of(int $accountId): Viewer;
}
