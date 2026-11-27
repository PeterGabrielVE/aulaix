<?php

declare(strict_types=1);

namespace AulaX\Ai\Infrastructure;

use AulaX\Ai\Application\AIServiceClient;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AI module's port to its HTTP adapter (constitution A-07).
 */
final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIServiceClient::class, fn ($app) => new HttpAIServiceClient(
            baseUrl: $app['config']->get('services.ai.base_url'),
            timeout: $app['config']->get('services.ai.timeout'),
        ));
    }
}
