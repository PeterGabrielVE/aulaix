<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\ShowLayout;

use AulaX\Workspace\Application\Port\CalendarSource;
use AulaX\Workspace\Application\Port\ViewerCapabilities;
use AulaX\Workspace\Domain\MenuEntry;
use AulaX\Workspace\Domain\Navigation;

/**
 * The layout of the open institution for the signed-in person (NAV-R02,
 * R03): their menu, the active year and its open term, their primary role,
 * and whether they have another institution to switch to.
 */
final readonly class ShowLayoutQuery
{
    public function __construct(
        private ViewerCapabilities $viewers,
        private CalendarSource $calendar,
    ) {}

    public function __invoke(int $accountId): LayoutView
    {
        $viewer = $this->viewers->of($accountId);
        $calendar = $this->calendar->snapshot();

        return new LayoutView(
            array_map(fn (array $group) => [
                'group' => $group['group']?->value,
                'entries' => array_map(fn (MenuEntry $entry) => $entry->value, $group['entries']),
            ], Navigation::for($viewer->capabilities)),
            $calendar->activeYearName,
            $calendar->openTerm,
            $viewer->capabilities->primaryRole,
            $viewer->activeInstitutions > 1,
        );
    }
}
