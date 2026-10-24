<?php

declare(strict_types=1);

namespace AulaX\Shared\Infrastructure;

use AulaX\Shared\Domain\Clock;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Reads Laravel's clock rather than the system's, so travelTo() and
 * freezeTime() in tests move the domain's time too.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return Date::now()->toDateTimeImmutable();
    }
}
