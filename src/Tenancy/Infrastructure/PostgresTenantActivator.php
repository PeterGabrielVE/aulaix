<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure;

use AulaX\Tenancy\Application\TenantActivationHook;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Application\TenantLeak;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * The institution lives in the transaction, never in the connection (ADR
 * 0011): PostgreSQL forgets it at COMMIT or ROLLBACK, and a rolled back
 * savepoint undoes a switch made inside it.
 */
final readonly class PostgresTenantActivator implements TenantActivator
{
    private ConnectionInterface $connection;

    /**
     * @param  iterable<TenantActivationHook>  $hooks
     */
    public function __construct(
        private InMemoryCurrentTenant $currentTenant,
        private iterable $hooks,
        ?ConnectionInterface $connection = null,
    ) {
        $this->connection = $connection ?? DB::connection();
    }

    public function forRequest(TenantContext $context, callable $handle, callable $keep): mixed
    {
        $this->connection->beginTransaction();

        try {
            $this->activate($context);
            $result = $handle();
        } catch (Throwable $failure) {
            $this->connection->rollBack();

            throw $failure;
        }

        $keep($result) ? $this->connection->commit() : $this->connection->rollBack();

        return $result;
    }

    public function activate(TenantContext $context): void
    {
        if ($this->connection->transactionLevel() === 0) {
            throw new LogicException('An institution can only be activated inside a transaction (ADR 0011).');
        }

        $this->switchTo($context);
    }

    public function deactivate(): void
    {
        if ($this->connection->transactionLevel() > 0) {
            RowLevelSecurity::clearInstitution();
        }

        $this->clearMemory();
    }

    public function within(TenantContext $context, callable $operation): mixed
    {
        $previous = $this->currentTenant->context();

        return $this->connection->transaction(function () use ($context, $operation, $previous) {
            $this->switchTo($context);

            try {
                $result = $operation();
            } catch (Throwable $failure) {
                // Rolling the savepoint back gives the database its previous
                // institution; only memory and the hooks need restoring.
                $previous === null ? $this->clearMemory() : $this->rememberAndNotify($previous);

                throw $failure;
            }

            $previous === null ? $this->deactivate() : $this->switchTo($previous);
            $this->ensureActive($previous);

            return $result;
        });
    }

    private function switchTo(TenantContext $context): void
    {
        RowLevelSecurity::setInstitution($context->institutionId->value);
        $this->rememberAndNotify($context);
    }

    private function rememberAndNotify(TenantContext $context): void
    {
        $this->currentTenant->set($context);

        foreach ($this->hooks as $hook) {
            $hook->onActivated($context);
        }
    }

    private function clearMemory(): void
    {
        $this->currentTenant->clear();

        foreach ($this->hooks as $hook) {
            $hook->onDeactivated();
        }
    }

    /**
     * @throws TenantLeak
     */
    private function ensureActive(?TenantContext $expected): void
    {
        $actual = RowLevelSecurity::currentSetting();

        if ($actual !== (string) ($expected?->institutionId->value ?? '')) {
            throw TenantLeak::expected($expected?->institutionId->value, $actual);
        }
    }
}
