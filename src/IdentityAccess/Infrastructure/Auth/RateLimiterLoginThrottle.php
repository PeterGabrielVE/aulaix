<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\LoginThrottle;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;

final readonly class RateLimiterLoginThrottle implements LoginThrottle
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(private RateLimiter $limiter) {}

    public function lockedOutFor(string $email, string $ip): ?int
    {
        $key = self::key($email, $ip);

        return $this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS) ? $this->limiter->availableIn($key) : null;
    }

    public function recordFailure(string $email, string $ip): void
    {
        $this->limiter->hit(self::key($email, $ip), self::DECAY_SECONDS);
    }

    public function clear(string $email, string $ip): void
    {
        $this->limiter->clear(self::key($email, $ip));
    }

    /**
     * Global since SPEC-004: one account per email, whatever the institution.
     */
    private static function key(string $email, string $ip): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim($email)).'|'.$ip);
    }
}
