<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

use DateTimeImmutable;

/**
 * The current time, for a domain that may not call now() (constitution A-02).
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
