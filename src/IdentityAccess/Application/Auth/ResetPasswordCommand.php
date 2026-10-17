<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $token,
        #[\SensitiveParameter] public string $password,
    ) {}
}
