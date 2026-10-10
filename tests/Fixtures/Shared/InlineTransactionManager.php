<?php

namespace Tests\Fixtures\Shared;

use AulaX\Shared\Application\TransactionManager;

/**
 * Runs the operation directly and remembers whether one is in progress, so a
 * test can assert that writes happened inside the transaction.
 */
final class InlineTransactionManager implements TransactionManager
{
    public bool $inTransaction = false;

    public function run(callable $operation): mixed
    {
        $this->inTransaction = true;

        try {
            return $operation();
        } finally {
            $this->inTransaction = false;
        }
    }
}
