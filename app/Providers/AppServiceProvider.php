<?php

namespace App\Providers;

use App\Contracts\AIServiceClient;
use App\Services\AI\HttpAIServiceClient;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);

        $this->app->singleton(AIServiceClient::class, fn () => new HttpAIServiceClient(
            baseUrl: config('services.ai.base_url'),
            timeout: config('services.ai.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
