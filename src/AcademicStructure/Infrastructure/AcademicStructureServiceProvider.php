<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Infrastructure;

use AulaX\AcademicStructure\Application\ActiveAssignmentBlocker;
use AulaX\AcademicStructure\Application\Port\AssignmentDirectory;
use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\Port\StructureStatistics;
use AulaX\AcademicStructure\Application\RemovalGuards;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\AcademicStructure\Infrastructure\Persistence\DatabaseStructureStatistics;
use AulaX\AcademicStructure\Infrastructure\Persistence\EloquentAssignmentDirectory;
use AulaX\AcademicStructure\Infrastructure\Persistence\EloquentSchoolYearStructureRepository;
use AulaX\IdentityAccess\Application\Account\DeletionBlocker;
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
        StaffDirectory::class => IdentityAccessStaffDirectory::class,
        AssignmentDirectory::class => EloquentAssignmentDirectory::class,
        StructureStatistics::class => DatabaseStructureStatistics::class,
    ];

    public function register(): void
    {
        // EAC-R09.1: IdentityAccess's extension point for account deletion (IDG-R08.4).
        $this->app->tag([ActiveAssignmentBlocker::class], DeletionBlocker::TAG);

        // Extension point (EAC-R02.6, R07.3): SPEC-010 and SPEC-011 tag their guards.
        $this->app->when(RemovalGuards::class)
            ->needs('$guards')
            ->giveTagged(RemovalGuard::TAG);
    }
}
