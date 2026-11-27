<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;

/**
 * How many SuperAdmins can still act, for SuperAdminRules (PLT-R03.5).
 */
final class ActiveSuperAdmins
{
    public static function count(OperatorRepository $operators): int
    {
        return count(array_filter(
            $operators->all(),
            fn (Operator $operator) => $operator->role === OperatorRole::SuperAdmin && $operator->status === OperatorStatus::Active,
        ));
    }
}
