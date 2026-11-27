<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * Ends an account's sessions on every domain (central and institutions),
 * e.g. on logout or after a password change (IDG-R06.2).
 */
interface SessionRevoker
{
    public function revokeAll(AccountId $account): void;

    /**
     * Every session but the one serving the current request.
     */
    public function revokeOthers(AccountId $account): void;
}
