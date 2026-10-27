<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use LogicException;

/**
 * Turns "an institution" into "the tenant": the CurrentTenant port, the
 * institution Row Level Security applies in the current transaction, and
 * whatever other modules hook onto it. One place, so they can never
 * disagree (ADR 0011).
 */
interface TenantActivator
{
    /**
     * Serves a request of an institution in one transaction, the only place
     * its institution lives (SPEC-015 RLT-R01): committed when $keep says
     * so, rolled back otherwise or when $handle throws.
     *
     * @template T
     *
     * @param  callable(): T  $handle
     * @param  callable(T): bool  $keep
     * @return T
     */
    public function forRequest(TenantContext $context, callable $handle, callable $keep): mixed;

    /**
     * The institution for the rest of the current transaction. Used by
     * forRequest() and by the test helpers; the application switches with
     * within() (an architecture test).
     *
     * @throws LogicException Outside a transaction, where nothing would hold it.
     */
    public function activate(TenantContext $context): void;

    /**
     * Leave the tenant, in memory and for the rest of the transaction.
     */
    public function deactivate(): void;

    /**
     * Act in another institution for a moment (SPEC-015 RLT-R02): in a
     * transaction, or a savepoint of the current one, then back to the
     * previous institution, or none, also when $operation fails.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     *
     * @throws TenantLeak When another institution is left active afterwards.
     */
    public function within(TenantContext $context, callable $operation): mixed;
}
