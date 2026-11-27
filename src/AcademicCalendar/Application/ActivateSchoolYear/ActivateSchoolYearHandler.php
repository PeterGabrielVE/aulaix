<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ActivateSchoolYear;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\SchoolYearSchedule;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager puts a planned year in force, which opens its first term, while
 * no other year is active (CAL-R02.1 – R02.3).
 */
final readonly class ActivateSchoolYearHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private SchoolYearSchedule $schedule,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $schoolYearId): void
    {
        $id = SchoolYearId::fromInt($schoolYearId);

        $this->transactions->run(function () use ($id) {
            $this->lock->lockInstitutionCalendar();
            $year = $this->years->byId($id) ?? throw SchoolYearNotFound::create();
            $this->schedule->assertCanActivate($this->years->othersOf($id));

            $from = $year->status()->value;
            $year->activate();

            $this->years->save($year);
            $this->audit->record(CalendarAudit::statusChanged('activated', $year, $from, $year->status()->value));
        });
    }
}
