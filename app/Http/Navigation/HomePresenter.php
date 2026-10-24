<?php

namespace App\Http\Navigation;

use AulaX\Workspace\Application\ShowHome\HomeView;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns Workspace's home screen into page props (SPEC-013): menu keys
 * become links, dates the institution's time. The texts of each block live
 * in the page.
 */
final class HomePresenter
{
    private const TIMEZONE = 'America/Caracas';

    /**
     * @return array<string, mixed>
     */
    public static function present(HomeView $home): array
    {
        return [
            'kind' => $home->kind,
            'pending' => $home->pending === null ? null : array_map(fn (array $item) => [
                'key' => $item['key'],
                'count' => $item['count'],
                'detail' => $item['detail'],
                'href' => route(MenuCatalog::routeOf($item['target'])),
            ], $home->pending),
            'indicators' => $home->indicators === null ? null : array_map(fn (array $indicator) => [
                'key' => $indicator['key'],
                'value' => $indicator['value'],
                'href' => $indicator['target'] === null ? null : route(MenuCatalog::routeOf($indicator['target'])),
            ], $home->indicators),
            'myClasses' => $home->myClasses,
            'myStudents' => $home->myStudents === null ? null : [
                'students' => array_map(fn (array $student) => $student + ['href' => route('my-students.show', $student['id'])], $home->myStudents['students']),
                'pendingCorrections' => $home->myStudents['pendingCorrections'],
            ],
            'recentActivity' => $home->recentActivity === null ? null : array_map(fn (array $entry) => [
                'occurredAt' => (new DateTimeImmutable($entry['occurredAt']))->setTimezone(new DateTimeZone(self::TIMEZONE))->format('d/m/Y H:i'),
                'actor' => $entry['actor'],
                'event' => $entry['event'],
            ], $home->recentActivity),
            'shortcuts' => array_map(MenuCatalog::shortcut(...), $home->shortcuts),
            'notices' => $home->notices,
        ];
    }
}
