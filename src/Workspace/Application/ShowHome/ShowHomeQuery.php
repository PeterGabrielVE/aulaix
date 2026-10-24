<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\ShowHome;

use AulaX\Workspace\Application\CalendarSnapshot;
use AulaX\Workspace\Application\Port\ActivitySource;
use AulaX\Workspace\Application\Port\CalendarSource;
use AulaX\Workspace\Application\Port\InstitutionSource;
use AulaX\Workspace\Application\Port\StaffSource;
use AulaX\Workspace\Application\Port\StructureSource;
use AulaX\Workspace\Application\Port\StudentSource;
use AulaX\Workspace\Application\Port\ViewerCapabilities;
use AulaX\Workspace\Domain\Capabilities;
use AulaX\Workspace\Domain\HomeBlock;
use AulaX\Workspace\Domain\HomeKind;
use AulaX\Workspace\Domain\HomePlan;
use AulaX\Workspace\Domain\MenuEntry;
use AulaX\Workspace\Domain\Navigation;

/**
 * A person's home screen (NAV-R04 – R09): the blocks HomePlan gives it,
 * each filled from its module. Only the sources of those blocks are asked
 * (NAV-R10.2), and every figure is read when the screen opens (NAV-R04.5).
 */
final readonly class ShowHomeQuery
{
    private const int RECENT_ENTRIES = 10;

    public function __construct(
        private ViewerCapabilities $viewers,
        private CalendarSource $calendar,
        private StructureSource $structure,
        private StudentSource $students,
        private StaffSource $staff,
        private InstitutionSource $institution,
        private ActivitySource $activity,
    ) {}

    public function __invoke(int $accountId, HomeKind $kind): HomeView
    {
        $capabilities = $this->viewers->of($accountId)->capabilities;
        $plan = HomePlan::for($kind, $capabilities);
        $has = fn (HomeBlock $block) => in_array($block, $plan, true);
        $academic = $has(HomeBlock::CalendarIndicators) || $has(HomeBlock::PendingStructure);

        $calendar = $academic ? $this->calendar->snapshot() : null;
        $notices = $kind === HomeKind::Student ? ['student'] : [];
        $structure = null;

        if ($has(HomeBlock::StructureIndicators) && $calendar?->hasActiveYear()) {
            $grades = null;

            if (HomePlan::scopesToCoordinatedGrades($kind)) {
                $grades = $this->structure->coordinatedGrades($accountId);
            }

            if ($grades === []) {
                $notices[] = 'no_coordinated_grades';
            } else {
                $structure = $this->structure->overview($grades);
            }
        } elseif ($kind === HomeKind::Coordinator && $calendar !== null && ! $calendar->hasActiveYear()) {
            $notices[] = 'no_active_year';
        }

        $coordinatorWithoutFigures = $kind === HomeKind::Coordinator && $notices !== [];

        return new HomeView(
            $kind->value,
            $coordinatorWithoutFigures || ! array_filter($plan, fn (HomeBlock $block) => $block->isPending())
                ? null
                : $this->pending($has, $calendar, $structure),
            $coordinatorWithoutFigures || $calendar === null ? null : $this->indicators($has, $capabilities, $calendar, $structure),
            $has(HomeBlock::MyClasses) ? $this->structure->classesOf($accountId) : null,
            $has(HomeBlock::MyStudents) ? $this->students->representedBy($accountId) : null,
            $has(HomeBlock::RecentActivity) ? $this->activity->recent(self::RECENT_ENTRIES) : null,
            array_values(array_map(
                fn (MenuEntry $entry) => $entry->value,
                array_filter(array_merge(...array_column(Navigation::for($capabilities), 'entries')), fn (MenuEntry $entry) => $entry !== MenuEntry::Home),
            )),
            $notices,
        );
    }

    /**
     * @param  callable(HomeBlock): bool  $has
     * @param  ?array<string, int>  $structure
     * @return list<array{key: string, count: int, target: string, detail: ?string}>
     */
    private function pending(callable $has, ?CalendarSnapshot $calendar, ?array $structure): array
    {
        $pending = [];
        $add = function (string $key, int $count, MenuEntry $target, ?string $detail = null) use (&$pending): void {
            if ($count > 0) {
                $pending[] = ['key' => $key, 'count' => $count, 'target' => $target->value, 'detail' => $detail];
            }
        };

        if ($has(HomeBlock::PendingYear) && $calendar !== null && ! $calendar->hasActiveYear()) {
            $add('no_active_year', 1, MenuEntry::Calendar, $calendar->hasPlannedYear ? 'planned' : null);
        }

        if ($structure !== null) {
            if ($has(HomeBlock::PendingYear)) {
                $add('grades_without_sections', $structure['gradesWithoutSections'], MenuEntry::Structure);
            }

            $add('sections_without_guide', $structure['sectionsWithoutGuide'], MenuEntry::Structure);
            $add('subjects_without_teacher', $structure['subjectsWithoutTeacher'], MenuEntry::Structure);
            $add('assignments_of_inactive_people', $structure['assignmentsOfInactivePeople'], MenuEntry::Structure);
        }

        if ($has(HomeBlock::PendingStudents)) {
            $requests = $this->students->pendingRequests();
            $add('pending_transfers', $requests['transfers'], MenuEntry::Transfers);
            $add('pending_corrections', $requests['corrections'], MenuEntry::Corrections);
        }

        if ($has(HomeBlock::PendingInvitations)) {
            $invitations = $this->staff->invitations();
            $add('pending_invitations', $invitations['pending'], MenuEntry::Users);
            $add('expired_invitations', $invitations['expired'], MenuEntry::Users);
        }

        if ($has(HomeBlock::PendingInstitutionData)) {
            foreach ($this->institution->gaps() as $gap) {
                $add("missing_{$gap}", 1, MenuEntry::Institution);
            }
        }

        return $pending;
    }

    /**
     * @param  callable(HomeBlock): bool  $has
     * @param  ?array<string, int>  $structure
     * @return list<array{key: string, value: mixed, target: ?string}>
     */
    private function indicators(callable $has, Capabilities $capabilities, CalendarSnapshot $calendar, ?array $structure): array
    {
        $target = fn (MenuEntry $entry) => $entry->isVisibleTo($capabilities) ? $entry->value : null;
        $indicators = [];

        if ($has(HomeBlock::CalendarIndicators)) {
            $indicators[] = ['key' => 'school_year', 'value' => $calendar->activeYearName, 'target' => $target(MenuEntry::Calendar)];
            $indicators[] = ['key' => 'open_term', 'value' => $calendar->openTerm, 'target' => $target(MenuEntry::Calendar)];
        }

        if ($structure !== null) {
            $indicators[] = ['key' => 'sections', 'value' => $structure['sections'], 'target' => $target(MenuEntry::Structure)];
            $indicators[] = ['key' => 'capacity', 'value' => $structure['capacity'], 'target' => $target(MenuEntry::Structure)];
        }

        if ($has(HomeBlock::StudentIndicators)) {
            $indicators[] = ['key' => 'active_students', 'value' => $this->students->activeStudents(), 'target' => $target(MenuEntry::Students)];
        }

        if ($has(HomeBlock::StaffIndicators)) {
            $indicators[] = ['key' => 'staff', 'value' => $this->staff->membersByRole(), 'target' => $target(MenuEntry::Users)];
        }

        return $indicators;
    }
}
