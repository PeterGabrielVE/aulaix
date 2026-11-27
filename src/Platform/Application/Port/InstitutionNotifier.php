<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

/**
 * Tells an institution's active Administradores that it was deactivated or
 * reactivated, and why (SPEC-014 PLT-R05.3).
 */
interface InstitutionNotifier
{
    public function statusChanged(int $institutionId, bool $active, string $reason): void;
}
