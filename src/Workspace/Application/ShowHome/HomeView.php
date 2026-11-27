<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\ShowHome;

/**
 * A home screen with the data of its blocks (NAV-R04 – R09). A block left
 * out of the plan is null: its data never left its module (NAV-R10.2).
 * Targets are keys of MenuEntry, so links come from the menu's catalog.
 */
final readonly class HomeView
{
    /**
     * @param  ?list<array{key: string, count: int, target: string, detail: ?string}>  $pending  Only the ones with cases; empty: «No hay pendientes».
     * @param  ?list<array{key: string, value: mixed, target: ?string}>  $indicators
     * @param  ?array{hasActiveYear: bool, teaching: list<array{section: string, subject: string}>, guiding: list<string>}  $myClasses
     * @param  ?array{students: list<array{id: int, name: string}>, pendingCorrections: int}  $myStudents
     * @param  ?list<array{occurredAt: string, actor: ?string, event: string}>  $recentActivity
     * @param  list<string>  $shortcuts  Menu entries the person sees, «Inicio» aside (NAV-R04.9).
     * @param  list<string>  $notices  student, no_active_year, no_coordinated_grades.
     */
    public function __construct(
        public string $kind,
        public ?array $pending,
        public ?array $indicators,
        public ?array $myClasses,
        public ?array $myStudents,
        public ?array $recentActivity,
        public array $shortcuts,
        public array $notices,
    ) {}
}
