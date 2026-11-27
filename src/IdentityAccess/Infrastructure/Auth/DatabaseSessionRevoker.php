<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * The database session driver keeps every domain's sessions in one table,
 * with their account, so they can all be ended at once. With any other
 * driver (tests use "array") there is nothing to delete here.
 */
final readonly class DatabaseSessionRevoker implements SessionRevoker
{
    public function __construct(
        private ConnectionInterface $connection,
        private Session $session,
        private Config $config,
    ) {}

    public function revokeAll(AccountId $account): void
    {
        if ($this->usesDatabase()) {
            $this->sessions()->where('user_id', $account->value)->delete();
        }
    }

    public function revokeOthers(AccountId $account): void
    {
        if ($this->usesDatabase()) {
            $this->sessions()
                ->where('user_id', $account->value)
                ->where('id', '!=', $this->session->getId())
                ->delete();
        }
    }

    private function usesDatabase(): bool
    {
        return $this->config->get('session.driver') === 'database';
    }

    private function sessions(): Builder
    {
        return $this->connection->table($this->config->get('session.table', 'sessions'));
    }
}
