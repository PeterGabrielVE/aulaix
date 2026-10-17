<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * Something that must be resolved before an account can be deleted
 * (IDG-R08.2 – R08.4). An extension point: SPEC-009 (only legal guardian of
 * a student) and SPEC-008 (only teacher of an assignment) register theirs.
 */
interface DeletionBlocker
{
    /**
     * Why the account can not be deleted yet; empty when nothing blocks it.
     *
     * @return list<string>
     */
    public function reasonsFor(AccountId $account): array;
}
