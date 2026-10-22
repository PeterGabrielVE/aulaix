<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Infrastructure;

use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\RemovalGuards;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\AcademicStructure\Infrastructure\Persistence\EloquentSchoolYearStructureRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AcademicStructure module's ports to their adapters (constitution A-07).
 */
final class AcademicStructureServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SchoolYearStructureRepository::class => EloquentSchoolYearStructureRepository::class,
        StructurePermissions::class => GateStructurePermissions::class,
    ];

    public function register(): void
    {
        // Extension point (EAC-R02.6, R07.3): SPEC-010 and SPEC-011 tag their guards.
        $this->app->when(RemovalGuards::class)
            ->needs('$guards')
            ->giveTagged(RemovalGuard::TAG);
    }
}
