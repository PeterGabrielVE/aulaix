<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

/**
 * Failed logins per email and client IP (IDG-R03.10): after five, that pair
 * is locked out for a while, even with the right password.
 */
interface LoginThrottle
{
    /**
     * Seconds until the pair may try again, or null when it may try now.
     */
    public function lockedOutFor(string $email, string $ip): ?int;

    public function recordFailure(string $email, string $ip): void;

    public function clear(string $email, string $ip): void;
}
