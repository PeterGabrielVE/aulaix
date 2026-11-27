<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public int $accountId,
        #[\SensitiveParameter] public string $currentPassword,
        #[\SensitiveParameter] public string $newPassword,
    ) {}
}
