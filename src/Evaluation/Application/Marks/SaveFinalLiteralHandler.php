<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\Literal;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\StudentMarks;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * The guide of a section of Primaria records the final literal of a
 * student, once the third term is closed (EVA-R04.3, R04.4).
 */
final readonly class SaveFinalLiteralHandler
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
    public function __invoke(SaveFinalLiteralCommand $command): void
    {
        $this->marks->record(
            $command->schoolYearId, $command->sectionId, $command->studentId, MarkKind::Literal, null,
            fn (StudentMarks $marks, bool $thirdTermClosed) => $marks->recordFinalLiteral(Literal::fromInput($command->literal), $command->description, $thirdTermClosed),
        );
    }
}
