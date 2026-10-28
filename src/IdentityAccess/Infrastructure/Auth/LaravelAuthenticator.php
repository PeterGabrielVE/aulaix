<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\BrowserBinding;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Session\Session;

final readonly class LaravelAuthenticator implements Authenticator
{
    public function __construct(
        private AuthFactory $auth,
        private Hasher $hasher,
        private Session $session,
        private BrowserBinding $browser,
    ) {}

    public function passwordMatches(AccountId $account, #[\SensitiveParameter] string $password): bool
    {
        $hash = AccountModel::query()->whereKey($account->value)->value('password');

        return is_string($hash) && $this->hasher->check($password, $hash);
    }

    public function logIn(AccountId $account): void
    {
        $this->auth->guard('web')->loginUsingId($account->value);
        $this->session->regenerate();
    }

    public function logOut(): void
    {
        $this->auth->guard('web')->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
        // SPEC-015 RLT-R04: handoff links issued before stop working here.
        $this->browser->rotate();
    }

    public function current(): ?AccountId
    {
        $id = $this->auth->guard('web')->id();

        return $id === null ? null : AccountId::fromInt((int) $id);
    }
}
