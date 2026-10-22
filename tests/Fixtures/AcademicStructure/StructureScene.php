<?php

namespace Tests\Fixtures\AcademicStructure;

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicCalendar\Domain\DateRange;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\AcademicStructure\Application\RemovalGuards;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionSettingsQuery;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionLevels;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\InstitutionType;
use AulaX\Tenancy\Domain\Subdomain;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;
use Tests\Fixtures\AcademicCalendar\CountingCalendarLock;
use Tests\Fixtures\AcademicCalendar\InMemorySchoolYearRepository;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Tenancy\InMemoryInstitutionRepository;

/**
 * Everything the structure use cases need, in memory: an institution with
 * some levels, its school years, the calendar lock, the removal guards and
 * the audit log.
 */
final class StructureScene
{
    public InlineTransactionManager $transactions;

    public RecordingAuditTrail $audit;

    public InMemorySchoolYearStructureRepository $structures;

    public InMemorySchoolYearRepository $years;

    public CountingCalendarLock $lock;

    public InMemoryInstitutionRepository $institutions;

    public InMemoryCurrentTenant $tenant;

    /** Students enrolled per section id, as SPEC-010 would tell. */
    public array $enrolled = [];

    /** Subject ids with grades, as SPEC-011 would tell. */
    public array $graded = [];

    /**
     * @param  list<string>  $levels
     */
    public function __construct(array $levels = ['primaria', 'media_general'])
    {
        $this->transactions = new InlineTransactionManager;
        $this->audit = new RecordingAuditTrail($this->transactions);
        $this->structures = new InMemorySchoolYearStructureRepository;
        $this->years = new InMemorySchoolYearRepository;
        $this->lock = new CountingCalendarLock($this->transactions);
        $institution = Institution::reconstitute(
            InstitutionId::fromInt(4),
            'Colegio Norte',
            Subdomain::fromString('norte'),
            InstitutionStatus::Active,
            InstitutionProfile::empty(),
            InstitutionType::Privada,
            $levels === [] ? null : InstitutionLevels::of($levels),
        );
        $this->institutions = new InMemoryInstitutionRepository($institution);
        $this->tenant = new InMemoryCurrentTenant;
        $this->tenant->set(TenantContext::of($institution));
    }

    public function year(int $id, int $start, SchoolYearStatus $status): void
    {
        $this->years->years[$id] = SchoolYear::reconstitute(
            SchoolYearId::fromInt($id),
            DateRange::fromStrings("{$start}-09-16", ($start + 1).'-07-15'),
            $status,
            [],
        );
    }

    public function loader(): StructureLoader
    {
        return new StructureLoader(
            $this->structures,
            new SchoolYearLookup($this->years),
            new InstitutionSettingsQuery($this->institutions, $this->tenant),
        );
    }

    public function guards(): RemovalGuards
    {
        $scene = $this;

        return new RemovalGuards([new class($scene) implements RemovalGuard
        {
            public function __construct(private readonly StructureScene $scene) {}

            public function enrolledIn(int $sectionId): int
            {
                return $this->scene->enrolled[$sectionId] ?? 0;
            }

            public function subjectsInUse(int $schoolYearId, string $grade, array $subjectIds): array
            {
                return array_values(array_intersect($subjectIds, $this->scene->graded));
            }
        }]);
    }
}
