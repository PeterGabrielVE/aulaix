<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ReopenTerm;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\TermNumber;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager reopens the last closed term of the active year, giving a
 * reason that goes to the audit log (CAL-R04.4 – R04.7, R07.1).
 */
final readonly class ReopenTermHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $schoolYearId, int $term, string $reason): void
    {
        $id = SchoolYearId::fromInt($schoolYearId);
        $number = TermNumber::of($term);

        $this->transactions->run(function () use ($id, $number, $reason) {
            $this->lock->lockInstitutionCalendar();
            $year = $this->years->byId($id) ?? throw SchoolYearNotFound::create();

            $year->reopenTerm($number, $reason);

            $this->years->save($year);
            $this->audit->record(CalendarAudit::term('reopened', $year, $number, 'closed', 'open', trim($reason)));
        });
    }
}
