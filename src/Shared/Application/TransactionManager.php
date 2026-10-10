<?php

declare(strict_types=1);

namespace AulaX\Shared\Application;

use Throwable;

/**
 * Runs a use case's writes atomically: all of them persist, or none do.
 */
interface TransactionManager
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     *
     * @throws Throwable Whatever the operation threw, after undoing its changes.
     */
    public function run(callable $operation): mixed;
}
