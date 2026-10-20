<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CloseTerm;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager closes the open term of the active year, once confirmed, which
 * opens the next one (CAL-R04.1, R04.2). Only this action closes a term: its
 * dates never do (CAL-R04.3).
 */
final readonly class CloseTermHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $schoolYearId, bool $confirmed): void
    {
        $id = SchoolYearId::fromInt($schoolYearId);

        $this->transactions->run(function () use ($id, $confirmed) {
            $this->lock->lockInstitutionCalendar();
            $year = $this->years->byId($id) ?? throw SchoolYearNotFound::create();

            $closed = $year->closeTerm($confirmed);

            $this->years->save($year);
            $this->audit->record(CalendarAudit::term('closed', $year, $closed, 'open', 'closed'));
        });
    }
}
