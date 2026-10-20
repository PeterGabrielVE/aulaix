<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearList;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
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
    ];

    public function register(): void
    {
        // Resolved per use, like the TransactionManager: it must lock on the
        // connection the use case's transaction runs on.
        $this->app->bind(CalendarLock::class, fn ($app) => new DatabaseCalendarLock(
            $app['db']->connection(),
            $app->make(CurrentTenant::class),
        ));
    }
}
