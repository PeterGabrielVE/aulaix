<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure;

use AulaX\AcademicCalendar\Application\Port\TermClosingGuard;
use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\Enrollment\Application\Port\GradedEnrollment;
use AulaX\Enrollment\Application\Port\OutcomeProposer;
use AulaX\Evaluation\Application\Hooks\GradedEnrollmentCheck;
use AulaX\Evaluation\Application\Hooks\GradeOutcomeProposer;
use AulaX\Evaluation\Application\Hooks\PendingGradesGuard;
use AulaX\Evaluation\Application\Hooks\SubjectsWithGrades;
use AulaX\Evaluation\Application\Pending\PendingGrades;
use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Evaluation\Domain\RevisionRepository;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentActivityGradesRepository;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentEvaluationPlanRepository;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentPendingGroupRepository;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentRevisionRepository;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentStudentMarksRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Evaluation module's ports to their adapters (constitution A-07)
 * and puts its implementations under the extension points of
 * AcademicCalendar, AcademicStructure and Enrollment (ADR 0012). Only this
 * provider tags them.
 */
final class EvaluationServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        EvaluationPlanRepository::class => EloquentEvaluationPlanRepository::class,
        ActivityGradesRepository::class => EloquentActivityGradesRepository::class,
        StudentMarksRepository::class => EloquentStudentMarksRepository::class,
        RevisionRepository::class => EloquentRevisionRepository::class,
        PendingGroupRepository::class => EloquentPendingGroupRepository::class,
        EvaluationPermissions::class => GateEvaluationPermissions::class,
        TeachingFacts::class => AcademicStructureTeachingFacts::class,
        EnrollmentFacts::class => EnrollmentEnrollmentFacts::class,
        CalendarFacts::class => AcademicCalendarFacts::class,
    ];

    public function register(): void
    {
        // SPEC-016 RVP-R04.7: the pending grades include the groups of pending subjects.
        // Bound, not only autowired: the container leaves the optional members null otherwise.
        $this->app->bind(PendingGrades::class, fn ($app) => new PendingGrades(
            $app->make(EvaluationPlanRepository::class),
            $app->make(ActivityGradesRepository::class),
            $app->make(StudentMarksRepository::class),
            $app->make(TeachingFacts::class),
            $app->make(EnrollmentFacts::class),
            $app->make(PendingMembers::class),
        ));

        // EVA-R12.2 – R12.4: a term does not close with grades pending, unless by exception.
        $this->app->tag([PendingGradesGuard::class], TermClosingGuard::TAG);
        // EVA-R08: «Prosecución» proposes the result of the year from the grades.
        $this->app->tag([GradeOutcomeProposer::class], OutcomeProposer::TAG);
        // EVA-R09.1: an enrollment with grades is annulled only by a manager.
        $this->app->tag([GradedEnrollmentCheck::class], GradedEnrollment::TAG);
        // EVA-R09.2: a subject with grades does not leave the curriculum.
        $this->app->tag([SubjectsWithGrades::class], RemovalGuard::TAG);
    }
}
