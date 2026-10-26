<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\PendingTotpSecrets;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Session\Session;

/**
 * Encrypted in the browser's session while it enrols; forgotten once the
 * TOTP is confirmed.
 */
final readonly class SessionPendingTotpSecrets implements PendingTotpSecrets
{
    private const string PREFIX = 'platform.enrolment.';

    public function __construct(
        private Session $session,
        private Encrypter $encrypter,
    ) {}

    public function put(string $tokenHash, #[\SensitiveParameter] string $secret): void
    {
        $this->session->put(self::PREFIX.$tokenHash, $this->encrypter->encryptString($secret));
    }

    public function get(string $tokenHash): ?string
    {
        $stored = $this->session->get(self::PREFIX.$tokenHash);

        return is_string($stored) ? $this->encrypter->decryptString($stored) : null;
    }

    public function forget(string $tokenHash): void
    {
        $this->session->forget(self::PREFIX.$tokenHash);
    }
}
