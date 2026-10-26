<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

use DateTimeImmutable;

/**
 * Time-based one-time codes (RFC 6238; PLT-R01.2, ADR 0010).
 */
interface TotpCodes
{
    /**
     * A new random secret, base32.
     */
    public function newSecret(): string;

    /**
     * The time step the code belongs to, one step either side of now; null
     * when it matches none. The caller refuses a step already used
     * (PLT-NF03).
     */
    public function verify(string $secret, string $code, DateTimeImmutable $now): ?int;

    /**
     * An SVG QR code for the authenticator app.
     */
    public function qrSvg(string $accountLabel, string $secret): string;
}
