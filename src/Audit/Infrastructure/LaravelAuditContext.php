<?php

declare(strict_types=1);

namespace AulaX\Audit\Infrastructure;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditOrigin;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the actor and origin from the running process. A request with a
 * matched route is a web change (runningInConsole() can't tell: tests and
 * queue workers run on the CLI too); otherwise it is a console change when a
 * command named its operator, or a system change.
 *
 * Bound as scoped: the operator is per request/job, never carried over.
 */
final class LaravelAuditContext implements AuditContext
{
    private ?string $consoleOperator = null;

    private ?int $platformOperator = null;

    public function __construct(private readonly Container $container) {}

    public function actorId(): ?int
    {
        $id = $this->container->make('auth')->guard()->id();

        return $id === null ? null : (int) $id;
    }

    public function origin(): AuditOrigin
    {
        if ($this->consoleOperator !== null) {
            return AuditOrigin::console($this->consoleOperator);
        }

        $request = $this->container->make('request');

        if ($this->platformOperator !== null) {
            return AuditOrigin::platform($request->ip(), $this->platformOperator);
        }

        return $request->route() !== null ? AuditOrigin::web($request->ip()) : AuditOrigin::system();
    }

    public function actAsConsoleOperator(string $operator): void
    {
        $this->consoleOperator = AuditOrigin::console($operator)->operator;
    }

    public function actAsPlatformOperator(int $operatorId): void
    {
        $this->platformOperator = $operatorId;
    }
}
