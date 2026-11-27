<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CloseSchoolYear;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearGuards;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\CalendarRuleViolation;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager closes the active year for good, once confirmed and with its
 * three terms closed (CAL-R02.4 – R02.6), and no student without its
 * result (SPEC-010 MAT-R16.2).
 */
final readonly class CloseSchoolYearHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private SchoolYearGuards $guards = new SchoolYearGuards([]),
    ) {}

    public function __invoke(int $schoolYearId, bool $confirmed): void
    {
        $id = SchoolYearId::fromInt($schoolYearId);

        $this->transactions->run(function () use ($id, $confirmed) {
            $this->lock->lockInstitutionCalendar();
            $year = $this->years->byId($id) ?? throw SchoolYearNotFound::create();
            $unresolved = $this->guards->unresolvedResults($id->value);

            if ($unresolved > 0) {
                throw CalendarRuleViolation::onField('status', "Hay {$unresolved} estudiantes sin resultado de prosecución: regístralo o retíralos antes de cerrar el año.");
            }

            $from = $year->status()->value;
            $year->close($confirmed);

            $this->years->save($year);
            $this->audit->record(CalendarAudit::statusChanged('closed', $year, $from, $year->status()->value));
        });
    }
}
