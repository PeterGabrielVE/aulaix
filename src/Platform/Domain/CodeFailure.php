<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use DateTimeImmutable;

/**
 * One failed TOTP or recovery code attempt on a platform operator (SPEC-017
 * RSP-R03.6). Keeps only the timestamp and IP for the lockout notice, never
 * the code typed.
 */
final readonly class CodeFailure
{
    public function __construct(
        public DateTimeImmutable $occurredAt,
        public string $ip,
    ) {}
}
