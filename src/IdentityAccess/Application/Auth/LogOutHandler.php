<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;

/**
 * Logging out ends every session of the account, on the central domain and
 * in every institution: with one identity, staying logged in to another
 * institution would be surprising (design decision 5).
 */
final readonly class LogOutHandler
{
    public function __construct(
        private Authenticator $authenticator,
        private SessionRevoker $sessions,
    ) {}

    public function __invoke(): void
    {
        $account = $this->authenticator->current();

        if ($account !== null) {
            $this->sessions->revokeAll($account);
        }

        $this->authenticator->logOut();
    }
}
