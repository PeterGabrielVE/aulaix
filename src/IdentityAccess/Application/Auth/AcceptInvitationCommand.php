<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

final readonly class AcceptInvitationCommand
{
    /**
     * @param  string  $subdomain  The institution's, as it came in the link, unvalidated.
     */
    public function __construct(
        public string $subdomain,
        public string $email,
        #[\SensitiveParameter] public string $token,
        #[\SensitiveParameter] public string $password,
    ) {}
}
