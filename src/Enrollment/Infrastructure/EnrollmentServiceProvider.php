<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure;

use AulaX\AcademicCalendar\Application\Port\SchoolYearGuard;
use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\Enrollment\Application\GradedEnrollments;
use AulaX\Enrollment\Application\Hooks\EndEnrollmentsOnWithdrawal;
use AulaX\Enrollment\Application\Hooks\EnrollmentRemovalGuard;
use AulaX\Enrollment\Application\Hooks\SectionScopedVisibility;
use AulaX\Enrollment\Application\Hooks\UnresolvedResultsGuard;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\GradedEnrollment;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Enrollment\Infrastructure\Persistence\EloquentEnrollmentList;
use AulaX\Enrollment\Infrastructure\Persistence\EloquentEnrollmentRepository;
use AulaX\Students\Application\Port\StudentVisibilityRule;
use AulaX\Students\Application\Port\WithdrawalHook;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Enrollment module's ports to their adapters (constitution A-07)
 * and puts its implementations under the extension points of Students and
 * AcademicStructure (ADR 0012). Only this provider tags them.
 */
final class EnrollmentServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        EnrollmentRepository::class => EloquentEnrollmentRepository::class,
        EnrollmentList::class => EloquentEnrollmentList::class,
        EnrollmentPermissions::class => GateEnrollmentPermissions::class,
        StudentFacts::class => StudentsStudentFacts::class,
        StructureFacts::class => AcademicStructureFacts::class,
        CalendarFacts::class => AcademicCalendarFacts::class,
    ];

    public function register(): void
    {
        $this->app->singleton(AgeRules::class, fn ($app) => new ConfigAgeRules(
            (string) $app->make(Config::class)->get('enrollment.age_cutoff'),
            (array) $app->make(Config::class)->get('enrollment.ages'),
        ));

        // MAT-R05: a withdrawn or transferred student ends its enrollments.
        $this->app->tag([EndEnrollmentsOnWithdrawal::class], WithdrawalHook::TAG);
        // MAT-R10: a section keeps its enrollments, and its capacity its current ones.
        $this->app->tag([EnrollmentRemovalGuard::class], RemovalGuard::TAG);
        // MAT-R16.2: a year does not close with students lacking their result, nor is deleted with enrollments.
        $this->app->tag([UnresolvedResultsGuard::class], SchoolYearGuard::TAG);
        // MAT-R08: Docente and Coordinador see the students of their sections or grades.
        $this->app->tag([SectionScopedVisibility::class], StudentVisibilityRule::TAG);

        // Extension point (MAT-R14.3): SPEC-011 tags whether an enrollment has grades.
        $this->app->when(GradedEnrollments::class)
            ->needs('$checks')
            ->giveTagged(GradedEnrollment::TAG);
    }

    public function boot(): void
    {
        // A wrong entry of config/enrollment.php stops the app here (MAT-R03.6).
        $this->app->make(AgeRules::class);
    }
}
