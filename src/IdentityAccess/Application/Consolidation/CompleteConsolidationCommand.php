<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

final readonly class CompleteConsolidationCommand
{
    public function __construct(
        public int $accountId,
        #[\SensitiveParameter] public string $code,
        #[\SensitiveParameter] public string $newPassword,
    ) {}
}
