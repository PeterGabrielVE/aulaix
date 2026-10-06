<?php

namespace App\Providers;

use App\Contracts\AIServiceClient;
use App\Services\AI\HttpAIServiceClient;
use App\Support\CurrentTenant;
use App\Support\RowLevelSecurity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        /*
         * The institution_id column of a tenant-scoped table (F1-01). It
         * defaults to the RLS session variable, so inserts that bypass
         * Eloquent's BelongsToInstitution (query builder, raw SQL) still
         * land in the current institution. Indexed because every query on
         * the table is filtered by it (the RLS policy adds the filter even
         * when the code doesn't) — pass `index: false` only when a
         * composite index or key already leads with institution_id.
         */
        Blueprint::macro('belongsToInstitution', function (bool $index = true) {
            /** @var Blueprint $this */
            $column = $this->foreignId('institution_id')
                ->default(DB::raw("nullif(current_setting('".RowLevelSecurity::SESSION_VARIABLE."', true), '')::bigint"));

            if ($index) {
                $column->index();
            }

            return $column->constrained()->cascadeOnDelete();
        });
    }
}
