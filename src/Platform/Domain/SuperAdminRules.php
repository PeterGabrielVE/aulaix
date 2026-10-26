<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Rules that look at all the operators, not one (PLT-R03.5, R03.6): the
 * platform always keeps an active SuperAdmin, and nobody takes away their
 * own role or access.
 */
final class SuperAdminRules
{
    /**
     * @param  bool  $demotes  Whether the change deactivates the target or takes its SuperAdmin role.
     * @param  int  $activeSuperAdmins  Active SuperAdmins before the change.
     *
     * @throws BusinessRuleViolation
     */
    public static function check(Operator $actor, Operator $target, bool $demotes, int $activeSuperAdmins): void
    {
        if (! $demotes) {
            return;
        }

        if ($actor->id !== null && $target->id !== null && $actor->id->equals($target->id)) {
            throw BusinessRuleViolation::onField('operator', 'No puedes quitarte el nivel ni desactivarte a ti mismo.');
        }

        if ($target->role === OperatorRole::SuperAdmin && $target->status === OperatorStatus::Active && $activeSuperAdmins <= 1) {
            throw BusinessRuleViolation::onField('operator', 'La plataforma no puede quedarse sin un Superadministrador activo.');
        }
    }
}
