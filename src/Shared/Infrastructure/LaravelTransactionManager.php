<?php

declare(strict_types=1);

namespace AulaX\Shared\Infrastructure;

use AulaX\Shared\Application\TransactionManager;
use Illuminate\Database\ConnectionInterface;

final class LaravelTransactionManager implements TransactionManager
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        return $this->connection->transaction(fn () => $operation());
    }
}
