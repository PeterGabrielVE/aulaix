<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Platform\Domain\Operator;

/**
 * Outcome of one locked TOTP or recovery code check (SPEC-017).
 */
final readonly class CodeAttemptResult
{
    private function __construct(
        public bool $accepted,
        public ?Operator $operator,
        public ?int $recoveryCodesLeft,
        public ?string $reason,
        public bool $blockedNow,
    ) {}

    public static function accepted(Operator $operator, ?int $recoveryCodesLeft): self
    {
        return new self(true, $operator, $recoveryCodesLeft, null, false);
    }

    public static function rejected(string $reason, bool $blockedNow): self
    {
        return new self(false, null, null, $reason, $blockedNow);
    }
}
