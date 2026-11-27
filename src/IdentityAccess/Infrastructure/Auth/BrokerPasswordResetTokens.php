<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\PasswordResetTokens;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;

/**
 * Laravel's password broker token table (password_reset_tokens), global
 * since SPEC-004 and readable only with no institution active. Its config
 * (auth.passwords.users) sets the 60 minute expiry and the 60 second
 * throttle.
 */
final readonly class BrokerPasswordResetTokens implements PasswordResetTokens
{
    public function __construct(private PasswordBrokerManager $brokers) {}

    public function create(AccountId $account): string
    {
        return $this->repository()->create(self::model($account));
    }

    public function isValid(AccountId $account, #[\SensitiveParameter] string $token): bool
    {
        return $this->repository()->exists(self::model($account), $token);
    }

    public function recentlyCreated(AccountId $account): bool
    {
        return $this->repository()->recentlyCreatedToken(self::model($account));
    }

    public function delete(AccountId $account): void
    {
        $this->repository()->delete(self::model($account));
    }

    private function repository(): TokenRepositoryInterface
    {
        return $this->brokers->broker('users')->getRepository();
    }

    private static function model(AccountId $account): AccountModel
    {
        return AccountModel::query()->findOrFail($account->value);
    }
}
