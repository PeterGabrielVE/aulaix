<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure;

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\SearchInstitutions\InstitutionDirectory;
use AulaX\Tenancy\Application\TenantActivationHook;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Infrastructure\Persistence\EloquentInstitutionDirectory;
use AulaX\Tenancy\Infrastructure\Persistence\EloquentInstitutionRepository;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Tenancy module's ports to their adapters (constitution A-07).
 */
final class TenancyServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        InstitutionRepository::class => EloquentInstitutionRepository::class,
        InstitutionDirectory::class => EloquentInstitutionDirectory::class,
    ];

    public function register(): void
    {
        // The port is an alias of the concrete singleton, so swapping the
        // instance (as tests do to simulate "no tenant") swaps it for every
        // reader of the port too.
        $this->app->singleton(InMemoryCurrentTenant::class);
        $this->app->alias(InMemoryCurrentTenant::class, CurrentTenant::class);

        $this->app->bind(TenantActivator::class, fn ($app) => new PostgresTenantActivator(
            $app->make(InMemoryCurrentTenant::class),
            $app->tagged(TenantActivationHook::class),
        ));
    }

    public function boot(): void
    {
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
                ->default(DB::raw(RowLevelSecurity::CURRENT_INSTITUTION_SQL));

            if ($index) {
                $column->index();
            }

            return $column->constrained()->cascadeOnDelete();
        });
    }
}
