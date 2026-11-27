<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

/**
 * The two levels of operator (SPEC-014): Support looks into institutions,
 * usage and the log, and resolves consolidations; SuperAdmin also manages
 * institutions and operators.
 */
enum OperatorRole: string
{
    case Support = 'support';
    case SuperAdmin = 'super_admin';

    public function canManage(): bool
    {
        return $this === self::SuperAdmin;
    }
}
