<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\MyAssignments;

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\Section;
use AulaX\Catalogs\Application\Subjects;

/**
 * A teacher's own classes in the active school year (EAC-R08.2): the
 * subjects they teach, where, and the sections they guide.
 */
final readonly class MyAssignmentsQuery
{
    public function __construct(
        private StructureLoader $loader,
        private SchoolYearLookup $years,
        private Subjects $subjects,
    ) {}

    public function __invoke(int $accountId): MyAssignmentsView
    {
        $year = $this->years->activeYear();

        if ($year === null) {
            return new MyAssignmentsView(null, [], []);
        }

        $sections = $this->loader->forYear($year)->sections();
        usort($sections, fn (Section $a, Section $b) => [array_search($a->grade(), Grade::cases(), true), $a->letter()->value]
            <=> [array_search($b->grade(), Grade::cases(), true), $b->letter()->value]);
        $names = $this->subjectNames($sections);
        $teaching = [];
        $guiding = [];

        foreach ($sections as $section) {
            $label = $section->grade()->name().' '.$section->letter()->value;

            foreach ($section->teachers() as $subjectId => $teacher) {
                if ($teacher === $accountId) {
                    $teaching[] = ['sectionId' => (int) $section->id(), 'section' => $label, 'level' => $section->grade()->level(), 'subjectId' => $subjectId, 'subject' => $names[$subjectId] ?? ''];
                }
            }

            if ($section->guideAccountId() === $accountId) {
                $guiding[] = ['sectionId' => (int) $section->id(), 'section' => $label, 'level' => $section->grade()->level()];
            }
        }

        return new MyAssignmentsView($year, $teaching, $guiding);
    }

    /**
     * @param  list<Section>  $sections
     * @return array<int, string>
     */
    private function subjectNames(array $sections): array
    {
        $names = [];
        $levels = array_unique(array_map(fn (Section $section) => $section->grade()->level(), $sections));

        foreach ($levels as $level) {
            foreach ($this->subjects->ofLevel($level) as $subject) {
                $names[$subject['id']] = $subject['name'];
            }
        }

        return $names;
    }
}
