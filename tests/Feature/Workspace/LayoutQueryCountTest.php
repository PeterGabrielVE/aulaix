<?php

use AulaX\Workspace\Application\ShowLayout\ShowLayoutQuery;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';

/**
 * SPEC-013 NAV-NF03: what the layout adds to every page costs at most five
 * database queries. The signed-in person's permissions are already loaded
 * for `auth.can` on every page; presenting the layout queries nothing.
 */
test('the layout adds five queries at most to a page', function () {
    $institution = tenant();
    storedSchoolYear(2026, 'active');
    $director = userWithRole($institution, 'Director');
    $director->assignRole('Docente');
    useTenant($institution);
    $this->actingAs($director = $director->fresh());
    $director->can('ver-plantel');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $layout = app(ShowLayoutQuery::class)($director->id);

    expect($layout->primaryRole)->toBe('Director')
        ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual(5);
})->group('perf');
