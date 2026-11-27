<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Auth\HandoffGrant;
use AulaX\IdentityAccess\Application\Port\HandoffTokens;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\InstitutionId;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use JsonException;

/**
 * The token is the encrypted and authenticated (APP_KEY) payload itself, so
 * it needs no table. Only its nonce is kept, in the cache, to burn it on
 * first use; production uses Redis so every server sees it (IDG-NF03).
 */
final readonly class CacheHandoffTokens implements HandoffTokens
{
    private const LIFETIME_SECONDS = 60;

    public function __construct(
        private Encrypter $encrypter,
        private Cache $cache,
        private Clock $clock,
    ) {}

    public function issue(AccountId $account, InstitutionId $institution, int $activeInstitutions = 1, string $browser = ''): string
    {
        return $this->encrypter->encryptString(json_encode([
            'account' => $account->value,
            'institution' => $institution->value,
            'institutions' => $activeInstitutions,
            'expires' => $this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS,
            'nonce' => bin2hex(random_bytes(16)),
            'browser' => $browser,
        ], JSON_THROW_ON_ERROR));
    }

    public function consume(#[\SensitiveParameter] string $token, InstitutionId $institution, string $browser = ''): ?HandoffGrant
    {
        try {
            $payload = json_decode($this->encrypter->decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($payload)
            || ($payload['institution'] ?? null) !== $institution->value
            || ! is_int($payload['account'] ?? null)
            || ! is_string($payload['nonce'] ?? null)
            || ($payload['expires'] ?? 0) < $this->clock->now()->getTimestamp()
            || ! hash_equals((string) ($payload['browser'] ?? ''), $browser)) {
            return null;
        }

        // add() only succeeds for the first caller: the token is burnt.
        if (! $this->cache->add('identity:handoff:'.$payload['nonce'], true, self::LIFETIME_SECONDS * 2)) {
            return null;
        }

        return new HandoffGrant(AccountId::fromInt($payload['account']), max(1, (int) ($payload['institutions'] ?? 1)));
    }
}
