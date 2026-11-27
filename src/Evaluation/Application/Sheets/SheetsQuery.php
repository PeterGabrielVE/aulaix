<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Sheets;

use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\ClassFact;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * «Notas»: the sheets of the active year a person may open (EVA-R07.3 –
 * R07.5): the subjects it teaches, those of the grades it coordinates, or
 * every one for whoever manages students. Only Media General has sheets of
 * activities; the sections of Primaria and Inicial have their literals and
 * reports (EVA-R04, R05).
 */
final readonly class SheetsQuery
{
    private const string LEVEL = 'media_general';

    private EvaluationAccess $access;

    public function __construct(
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
        private EvaluationPermissions $permissions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @throws AuthorizationDenied
     */
    public function __invoke(): SheetList
    {
        $account = $this->access->ensureCanView();
        $year = $this->calendar->activeYear();

        if ($year === null) {
            return new SheetList(null, 1, []);
        }

        $taught = array_map(fn (ClassFact $class) => "{$class->sectionId}:{$class->subjectId}", $this->teaching->taughtClasses($account));
        $coordinated = $this->teaching->coordinatedGrades($account);
        $seesAll = $this->permissions->seesAll();
        $sheets = [];

        foreach ($this->teaching->classesOf($year) as $class) {
            $teaches = in_array("{$class->sectionId}:{$class->subjectId}", $taught, true);

            if ($class->level === self::LEVEL && ($teaches || $seesAll || in_array($class->grade, $coordinated, true))) {
                $sheets[] = new SheetEntry($class->sectionId, $class->section, $class->subjectId, $class->subject, $teaches);
            }
        }

        return new SheetList($year, $this->termToOpen($year), $sheets, $this->sections($account, $year, $coordinated, $seesAll));
    }

    /**
     * The sections of Primaria and Inicial the person guides, coordinates or,
     * managing students, every one (EVA-R07.2 – R07.5).
     *
     * @param  list<string>  $coordinated
     * @return list<SectionEntry>
     */
    private function sections(int $account, int $year, array $coordinated, bool $seesAll): array
    {
        $sections = [];

        foreach ($this->teaching->sectionsOf($year) as $section) {
            $kind = match ($section->level) {
                MarkKind::Literal->level() => MarkKind::Literal,
                MarkKind::Report->level() => MarkKind::Report,
                default => null,
            };

            if ($kind === null) {
                continue;
            }

            $guides = $this->teaching->guides($account, $section->id);

            if ($guides || $seesAll || in_array($section->grade, $coordinated, true)) {
                $sections[] = new SectionEntry($section->id, "{$section->gradeName} {$section->letter}", $kind->value, $guides);
            }
        }

        return $sections;
    }

    private function termToOpen(int $schoolYearId): int
    {
        $lastClosed = 1;

        foreach ([1, 2, 3] as $term) {
            $status = $this->calendar->termStatus($schoolYearId, $term);

            if ($status === 'open') {
                return $term;
            }

            if ($status === 'closed') {
                $lastClosed = $term;
            }
        }

        return $lastClosed;
    }
}
