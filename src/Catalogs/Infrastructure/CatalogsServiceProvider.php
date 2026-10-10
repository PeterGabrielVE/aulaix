<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure;

use AulaX\Catalogs\Application\Port\GeographySource;
use AulaX\Catalogs\Application\Port\GeographyStore;
use AulaX\Catalogs\Application\Port\SubjectSource;
use AulaX\Catalogs\Application\Port\SubjectStore;
use AulaX\Catalogs\Infrastructure\Persistence\EloquentGeographyStore;
use AulaX\Catalogs\Infrastructure\Persistence\EloquentSubjectStore;
use AulaX\Catalogs\Infrastructure\Source\JsonGeographySource;
use AulaX\Catalogs\Infrastructure\Source\MppeSubjectSource;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Catalogs module's ports to their adapters (constitution A-07).
 */
final class CatalogsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SubjectSource::class => MppeSubjectSource::class,
        SubjectStore::class => EloquentSubjectStore::class,
        GeographyStore::class => EloquentGeographyStore::class,
    ];

    public function register(): void
    {
        $this->app->bind(GeographySource::class, fn ($app) => new JsonGeographySource(
            $app->databasePath('seeders/data/venezuela-divisions.json'),
        ));
    }
}
