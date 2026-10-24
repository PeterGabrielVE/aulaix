<?php

declare(strict_types=1);

namespace AulaX\Students\Application\GuardianPortal;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Domain\Guardian\GuardianRepository;

/**
 * The guardian an account is linked to in the open institution.
 */
final class LinkedGuardian
{
    /**
     * @return int The guardian's id.
     *
     * @throws AuthorizationDenied When the account is linked to no guardian here.
     */
    public static function of(GuardianRepository $guardians, int $accountId): int
    {
        $guardian = $guardians->byAccount($accountId) ?? throw new AuthorizationDenied('Tu cuenta no representa a ningún estudiante de este plantel.');

        return (int) $guardian->id()?->value;
    }
}
