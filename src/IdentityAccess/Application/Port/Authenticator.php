<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * The session of the domain serving the request: who is logged in to it,
 * and checking a password against an account's stored hash, which never
 * enters the domain.
 */
interface Authenticator
{
    public function passwordMatches(AccountId $account, #[\SensitiveParameter] string $password): bool;

    /**
     * Start the session as the account, with a fresh session id.
     */
    public function logIn(AccountId $account): void;

    public function logOut(): void;

    public function current(): ?AccountId;
}
