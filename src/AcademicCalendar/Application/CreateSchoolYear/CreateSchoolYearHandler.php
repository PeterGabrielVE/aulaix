<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CreateSchoolYear;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\SchoolYearSchedule;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager creates the next school year, planned, with its three terms
 * (CAL-R01). The rules across years are checked under the calendar lock of
 * the institution (ADR 0007), and the creation is recorded (CAL-R07.1).
 */
final readonly class CreateSchoolYearHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private SchoolYearSchedule $schedule,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @return int The new year's id.
     */
    public function __invoke(CreateSchoolYearCommand $command): int
    {
        $year = SchoolYear::plan(SchoolYearDates::fromStrings($command->startsOn, $command->endsOn, $command->terms));

        return $this->transactions->run(function () use ($year) {
            $this->lock->lockInstitutionCalendar();
            $this->schedule->assertCanPlan($year, $this->years->othersOf(null));

            $id = $this->years->save($year);
            $this->audit->record(CalendarAudit::created($year));

            return $id->value;
        });
    }
}
