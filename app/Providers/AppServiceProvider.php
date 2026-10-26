<?php

namespace App\Providers;

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\TenantActivationHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag(SpatiePermissionTeamHook::class, TenantActivationHook::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // SPEC-009 EST-NF04: per institution, so transfer requests can not be
        // used to find out where students are.
        RateLimiter::for('transfer-requests', fn () => Limit::perHour(20)->by(
            'transfer-requests:'.$this->app->make(CurrentTenant::class)->institutionId()?->value,
        ));

        // SPEC-014 PLT-NF03: every attempt counts, whatever failed (V5).
        RateLimiter::for('platform-login', fn (Request $request) => Limit::perMinute(5)->by(
            'platform-login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip(),
        ));
        RateLimiter::for('platform-links', fn (Request $request) => Limit::perMinute(10)->by('platform-links:'.$request->ip()));
    }
}
