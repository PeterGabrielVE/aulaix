<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure;

use AulaX\AcademicCalendar\Application\CalendarContext\CalendarContexts;
use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearList;
use AulaX\AcademicCalendar\Application\Port\SchoolYearGuard;
use AulaX\AcademicCalendar\Application\Port\TermClosingGuard;
use AulaX\AcademicCalendar\Application\SchoolYearGuards;
use AulaX\AcademicCalendar\Application\TermClosingGuards;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Infrastructure\Persistence\DatabaseCalendarContexts;
use AulaX\AcademicCalendar\Infrastructure\Persistence\DatabaseCalendarLock;
use AulaX\AcademicCalendar\Infrastructure\Persistence\EloquentSchoolYearList;
use AulaX\AcademicCalendar\Infrastructure\Persistence\EloquentSchoolYearRepository;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AcademicCalendar module's ports to their adapters (constitution A-07).
 */
final class AcademicCalendarServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SchoolYearRepository::class => EloquentSchoolYearRepository::class,
        SchoolYearList::class => EloquentSchoolYearList::class,
        CalendarContexts::class => DatabaseCalendarContexts::class,
    ];

    public function register(): void
    {
        // Extension point (ADR 0012): SPEC-010 guards closing and deleting a year.
        // Bound, not only contextual: the handlers' default (no guards) would win otherwise.
        $this->app->bind(SchoolYearGuards::class, fn ($app) => new SchoolYearGuards($app->tagged(SchoolYearGuard::TAG)));
        // SPEC-011 guards closing a term with grades pending.
        $this->app->bind(TermClosingGuards::class, fn ($app) => new TermClosingGuards($app->tagged(TermClosingGuard::TAG)));

        // Resolved per use, like the TransactionManager: it must lock on the
        // connection the use case's transaction runs on.
        $this->app->bind(CalendarLock::class, fn ($app) => new DatabaseCalendarLock(
            $app['db']->connection(),
            $app->make(CurrentTenant::class),
        ));
    }
}
