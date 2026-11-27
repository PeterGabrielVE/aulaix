<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

interface OperatorPasswords
{
    public function hash(#[\SensitiveParameter] string $password): string;

    /**
     * With no hash (unknown operator), still compares against a dummy one,
     * so a missing account answers no faster (V5).
     */
    public function check(#[\SensitiveParameter] string $password, ?string $hash): bool;
}
