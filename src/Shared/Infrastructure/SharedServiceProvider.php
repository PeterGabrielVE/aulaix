<?php

declare(strict_types=1);

namespace AulaX\Shared\Infrastructure;

use AulaX\Shared\Application\EventBus;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Clock;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the shared kernel's ports to their Laravel adapters (constitution A-07).
 */
final class SharedServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        Clock::class => SystemClock::class,
    ];

    public function register(): void
    {
        // Resolved per use, not as singletons: the default connection can
        // change between resolutions (tests, multi-connection commands).
        $this->app->bind(TransactionManager::class, fn ($app) => new LaravelTransactionManager(
            $app['db']->connection(),
        ));

        $this->app->bind(EventBus::class, fn ($app) => new LaravelEventBus(
            $app->make(Dispatcher::class),
            $app['db']->connection(),
        ));
    }
}
