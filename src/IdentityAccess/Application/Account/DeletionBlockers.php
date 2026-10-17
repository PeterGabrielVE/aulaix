<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * Every registered DeletionBlocker, asked together (IDG-R08.2 – R08.4,
 * checked again at the end of the grace period, IDG-R08.10).
 */
final readonly class DeletionBlockers
{
    /**
     * @param  iterable<DeletionBlocker>  $blockers
     */
    public function __construct(private iterable $blockers) {}

    /**
     * @return list<string>
     */
    public function reasonsFor(AccountId $account): array
    {
        $reasons = [];

        foreach ($this->blockers as $blocker) {
            $reasons = [...$reasons, ...$blocker->reasonsFor($account)];
        }

        return $reasons;
    }
}
