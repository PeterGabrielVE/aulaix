<?php

use AulaX\Workspace\Application\CalendarSnapshot;
use AulaX\Workspace\Application\ShowLayout\ShowLayoutQuery;
use AulaX\Workspace\Application\Viewer;
use AulaX\Workspace\Domain\Capabilities;
use Tests\Fixtures\Workspace\FixedCalendarSource;
use Tests\Fixtures\Workspace\FixedViewerCapabilities;

/**
 * SPEC-013 NAV-R02.2 – R02.6, R03.1 – R03.4: what the layout shows each
 * person on every page of the institution.
 */
function layoutFor(Viewer $viewer, CalendarSnapshot $calendar): object
{
    return (new ShowLayoutQuery(new FixedViewerCapabilities([5 => $viewer]), new FixedCalendarSource($calendar)))(5);
}

it('shows the menu, the year and term, the primary role and the switch', function () {
    $layout = layoutFor(
        new Viewer(new Capabilities(['ver-estructura'], ['Docente'], 'Docente'), activeInstitutions: 2),
        new CalendarSnapshot('2026-2027', openTerm: 2, hasPlannedYear: false),
    );

    expect($layout->navigation)->toBe([
        ['group' => null, 'entries' => ['home']],
        ['group' => 'academic', 'entries' => ['structure', 'my_assignments']],
    ])
        ->and($layout->yearName)->toBe('2026-2027')
        ->and($layout->openTerm)->toBe(2)
        ->and($layout->primaryRole)->toBe('Docente')
        ->and($layout->canSwitchInstitution)->toBeTrue();
});

it('says there is no active year, and offers no switch to a member of one institution', function () {
    $layout = layoutFor(
        new Viewer(new Capabilities([], [], null), activeInstitutions: 1),
        new CalendarSnapshot(null, openTerm: null, hasPlannedYear: true),
    );

    expect($layout->yearName)->toBeNull()
        ->and($layout->openTerm)->toBeNull()
        ->and($layout->primaryRole)->toBeNull()
        ->and($layout->canSwitchInstitution)->toBeFalse()
        ->and($layout->navigation)->toBe([['group' => null, 'entries' => ['home']]]);
});
