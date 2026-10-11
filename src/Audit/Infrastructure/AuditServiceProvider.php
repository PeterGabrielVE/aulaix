<?php

declare(strict_types=1);

namespace AulaX\Audit\Infrastructure;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\ListEntries\AuditLog;
use AulaX\Shared\Domain\Clock;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Audit module's ports to their adapters (constitution A-07).
 */
final class AuditServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AuditLog::class => DatabaseAuditLog::class,
    ];

    public function register(): void
    {
        $this->app->scoped(AuditContext::class, LaravelAuditContext::class);

        // Resolved per use, like the TransactionManager: it must write on the
        // connection the audited change is using.
        $this->app->bind(AuditTrail::class, fn ($app) => new DatabaseAuditTrail(
            $app['db']->connection(),
            $app->make(AuditContext::class),
            $app->make(Clock::class),
        ));
    }
}
