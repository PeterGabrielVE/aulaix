<?php

declare(strict_types=1);

namespace AulaX\Students\Application\GuardianPortal;

use AulaX\Students\Domain\Guardian\GuardianRepository;

/**
 * Whether the account is linked to a guardian of the open institution: the
 * menu offers «Mis representados» then.
 */
final readonly class IsGuardianQuery
{
    public function __construct(private GuardianRepository $guardians) {}

    public function __invoke(int $accountId): bool
    {
        return $this->guardians->byAccount($accountId) !== null;
    }
}
