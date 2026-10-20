<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\DeleteSchoolYear;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\CalendarRuleViolation;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager deletes a year created by mistake, with its terms, once
 * confirmed; only while it was never activated (CAL-R09).
 */
final readonly class DeleteSchoolYearHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $schoolYearId, bool $confirmed): void
    {
        if (! $confirmed) {
            throw CalendarRuleViolation::confirmationMissing();
        }

        $id = SchoolYearId::fromInt($schoolYearId);

        $this->transactions->run(function () use ($id) {
            $this->lock->lockInstitutionCalendar();
            $year = $this->years->byId($id) ?? throw SchoolYearNotFound::create();

            if (! $year->canBeDeleted()) {
                throw CalendarRuleViolation::onField('status', 'Solo se puede eliminar un año planificado.');
            }

            $this->audit->record(CalendarAudit::statusChanged('deleted', $year, $year->status()->value, 'deleted'));
            $this->years->delete($id);
        });
    }
}
