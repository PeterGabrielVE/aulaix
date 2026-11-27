<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure;

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Application\SearchInstitutions\InstitutionDirectory;
use AulaX\Tenancy\Application\TenantActivationHook;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Infrastructure\Files\LaravelInstitutionFiles;
use AulaX\Tenancy\Infrastructure\Persistence\EloquentInstitutionDirectory;
use AulaX\Tenancy\Infrastructure\Persistence\EloquentInstitutionRepository;
use AulaX\Tenancy\Infrastructure\Persistence\EloquentSignatureRepository;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use AulaX\Tenancy\Infrastructure\Queue\EnsureNoTenantOnJobStart;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        SignatureRepository::class => EloquentSignatureRepository::class,
        InstitutionPermissions::class => GateInstitutionPermissions::class,
    ];

    public function register(): void
    {
        // The port is an alias of the concrete instance, so swapping the
        // instance (as tests do to simulate "no tenant") swaps it for every
        // reader of the port too. Scoped (SPEC-015 RLT-R03.1): a queue
        // worker forgets it between jobs.
        $this->app->scoped(InMemoryCurrentTenant::class);
        $this->app->alias(InMemoryCurrentTenant::class, CurrentTenant::class);

        $this->app->bind(InstitutionFiles::class, fn ($app) => new LaravelInstitutionFiles(
            $app->make('filesystem')->disk(config('tenancy.files_disk')),
            $app->make(CurrentTenant::class),
        ));

        $this->app->bind(TenantActivator::class, fn ($app) => new PostgresTenantActivator(
            $app->make(InMemoryCurrentTenant::class),
            $app->tagged(TenantActivationHook::class),
        ));
    }

    public function boot(): void
    {
        Event::listen(JobProcessing::class, EnsureNoTenantOnJobStart::class);

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
