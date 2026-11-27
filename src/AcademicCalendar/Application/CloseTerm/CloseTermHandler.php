<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CloseTerm;

use AulaX\AcademicCalendar\Application\CalendarAudit;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Application\TermClosingGuards;
use AulaX\AcademicCalendar\Domain\CalendarRuleViolation;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\TermStatus;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager closes the open term of the active year, once confirmed, which
 * opens the next one (CAL-R04.1, R04.2). Only this action closes a term: its
 * dates never do (CAL-R04.3). With grades pending, only by exception, with a
 * justification (SPEC-011 EVA-R12.2 – R12.4).
 */
final readonly class CloseTermHandler
{
    public function __construct(
        private SchoolYearRepository $years,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private TermClosingGuards $guards = new TermClosingGuards([]),
    ) {}

    /**
     * @param  ?string  $exceptionJustification  Null: no exception asked for.
     */
    public function __invoke(int $schoolYearId, bool $confirmed, ?string $exceptionJustification = null): void
    {
        $id = SchoolYearId::fromInt($schoolYearId);

        $this->transactions->run(function () use ($id, $confirmed, $exceptionJustification) {
            $this->lock->lockInstitutionCalendar();
            $year = $this->years->byId($id) ?? throw SchoolYearNotFound::create();
            $open = self::openTerm($year);

            if ($confirmed && $open !== null) {
                $this->guardPending($id->value, $open, $exceptionJustification);
            }

            $closed = $year->closeTerm($confirmed);

            $this->years->save($year);
            $this->audit->record(CalendarAudit::term('closed', $year, $closed, 'open', 'closed'));
        });
    }

    private function guardPending(int $schoolYearId, int $term, ?string $exceptionJustification): void
    {
        $pending = $this->guards->pendingGrades($schoolYearId, $term);

        if ($pending === 0) {
            return;
        }

        if ($exceptionJustification === null) {
            throw CalendarRuleViolation::onField('term', "Hay {$pending} notas pendientes: complétalas o cierra por excepción con una justificación.");
        }

        if (trim($exceptionJustification) === '') {
            throw CalendarRuleViolation::onField('exception_justification', 'Indica la justificación para cerrar por excepción.');
        }

        $this->guards->fillPending($schoolYearId, $term, trim($exceptionJustification));
    }

    private static function openTerm(SchoolYear $year): ?int
    {
        foreach ($year->terms() as $term) {
            if ($term->status === TermStatus::Open) {
                return $term->number->value;
            }
        }

        return null;
    }
}
