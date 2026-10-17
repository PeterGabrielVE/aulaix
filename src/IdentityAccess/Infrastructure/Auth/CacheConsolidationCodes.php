<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\ConsolidationCodes;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\Clock;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Codes kept hashed in the cache (Redis in production), with no table
 * (design: «Código de consolidación», approved 2026-10-16).
 */
final readonly class CacheConsolidationCodes implements ConsolidationCodes
{
    private const LIFETIME_SECONDS = 15 * 60;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private Cache $cache,
        private Clock $clock,
    ) {}

    public function issue(AccountId $account): string
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $this->cache->put(self::key($account), [
            'hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires' => $this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS,
        ], self::LIFETIME_SECONDS);

        return $code;
    }

    public function verify(AccountId $account, #[\SensitiveParameter] string $code): bool
    {
        $entry = $this->cache->get(self::key($account));
        $now = $this->clock->now()->getTimestamp();

        if (! is_array($entry) || $entry['expires'] < $now) {
            return false;
        }

        if (hash_equals($entry['hash'], hash('sha256', $code))) {
            $this->cache->forget(self::key($account));

            return true;
        }

        $entry['attempts']++;

        $entry['attempts'] >= self::MAX_ATTEMPTS
            ? $this->cache->forget(self::key($account))
            : $this->cache->put(self::key($account), $entry, $entry['expires'] - $now);

        return false;
    }

    private static function key(AccountId $account): string
    {
        return 'identity:consolidation-code:'.$account->value;
    }
}
