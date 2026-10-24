<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

final readonly class LogInCommand
{
    /**
     * @param  string  $ip  The client's, for the throttle (IDG-R03.10).
     */
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
        public string $ip,
    ) {}
}
