<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\StudentMarks;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * The guide of a section of Inicial records the descriptive report of a
 * student in a term (EVA-R05.1 – R05.3); the audit log never keeps its
 * text (EVA-R10.2).
 */
final readonly class SaveReportHandler
{
    private GuideMarks $marks;

    public function __construct(
        StudentMarksRepository $marks,
        TeachingFacts $teaching,
        CalendarFacts $calendar,
        EnrollmentFacts $enrollments,
        EvaluationPermissions $permissions,
        Clock $clock,
        AuditTrail $audit,
        TransactionManager $transactions,
    ) {
        $this->marks = new GuideMarks($marks, $teaching, $calendar, $enrollments, $permissions, $clock, $audit, $transactions);
    }

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws EvaluationNotFound
     */
    public function __invoke(SaveReportCommand $command): void
    {
        $this->marks->record(
            $command->schoolYearId, $command->sectionId, $command->studentId, MarkKind::Report, $command->term,
            fn (StudentMarks $marks) => $marks->recordReport($command->term, $command->text),
        );
    }
}
