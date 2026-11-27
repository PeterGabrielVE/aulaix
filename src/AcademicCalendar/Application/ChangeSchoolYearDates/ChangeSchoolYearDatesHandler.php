<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ChangeSchoolYearDates;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\SchoolYearSchedule;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager corrects the dates of a year not closed, and of its terms not
 * closed (CAL-R03), still apart from the other years of the institution
 * (CAL-R01.9). Only the dates that moved are recorded (CAL-R07.1).
 */
final readonly class ChangeSchoolYearDatesHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private SchoolYearSchedule $schedule,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(ChangeSchoolYearDatesCommand $command): void
    {
        $dates = SchoolYearDates::fromStrings($command->startsOn, $command->endsOn, $command->terms);
        $id = SchoolYearId::fromInt($command->schoolYearId);

        $this->transactions->run(function () use ($id, $dates) {
            $this->lock->lockInstitutionCalendar();
            $year = $this->years->byId($id) ?? throw SchoolYearNotFound::create();
            $before = CalendarAudit::dates($year);

            $year->changeDates($dates);
            $this->schedule->assertDatesFit($year, $this->years->othersOf($id));

            $this->years->save($year);
            $this->audit->record(CalendarAudit::datesChanged($before, $year));
        });
    }
}
