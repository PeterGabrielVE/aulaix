<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use DateTimeImmutable;

final readonly class AccountView
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $pendingEmail,
        public bool $pendingDeletion,
        public ?DateTimeImmutable $deletionDueAt,
    ) {}
}
