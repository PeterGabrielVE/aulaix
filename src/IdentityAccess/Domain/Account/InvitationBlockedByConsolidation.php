<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * An account whose email still has other historical accounts can not join
 * an institution (SPEC-015 RLT-R06): the membership would land on an
 * account that may not be its owner's. It only speaks of the person's own
 * email (RLT-NF05).
 */
final class InvitationBlockedByConsolidation extends BusinessRuleViolation
{
    public ConsolidationState $state;

    public static function in(ConsolidationState $state): self
    {
        $refused = self::onField('email', match ($state) {
            ConsolidationState::Pending => 'Antes de aceptar, unifica tus cuentas con este correo.',
            ConsolidationState::Review => 'Soporte está revisando las cuentas con este correo. Podrás aceptar la invitación cuando termine.',
            ConsolidationState::Separated => 'Hay varias cuentas con este correo. Pide al plantel que te invite con otro correo.',
        });
        $refused->state = $state;

        return $refused;
    }
}
