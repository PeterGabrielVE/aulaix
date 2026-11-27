<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\TotpCodes;
use DateTimeImmutable;

/**
 * In-memory stand-in for the Platform port of the same role (SPEC-014 tests).
 */
final class FakeTotpCodes implements TotpCodes
{
    /** @var array<string, array<string, int>> Secret => code => step. */
    public array $codes = [];

    public int $verifications = 0;

    public int $secrets = 0;

    public function newSecret(): string
    {
        return 'SECRET'.(++$this->secrets);
    }

    public function verify(string $secret, string $code, DateTimeImmutable $now): ?int
    {
        $this->verifications++;

        return $this->codes[$secret][$code] ?? null;
    }

    public function qrSvg(string $accountLabel, string $secret): string
    {
        return "<svg>{$accountLabel}:{$secret}</svg>";
    }
}
