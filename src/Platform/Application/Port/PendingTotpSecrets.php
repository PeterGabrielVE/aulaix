<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

/**
 * The TOTP secret shown with the QR while an operator enrols, kept in its
 * browser session until a first code confirms it; only then is it stored
 * (PLT-R01.6).
 */
interface PendingTotpSecrets
{
    public function put(string $tokenHash, #[\SensitiveParameter] string $secret): void;

    public function get(string $tokenHash): ?string;

    public function forget(string $tokenHash): void;
}
