<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Pending;

use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Application\Port\SectionInfo;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\StudentMarksRepository;

/**
 * What every section still lacks in a term (EVA-R12.1): in Media General,
 * the grades of each activity and the plans not yet made; in Primaria and
 * Inicial, the literals and reports. Only for the students currently in the
 * section, and only the activities that apply to them (EVA-R12.5). No
 * permission is checked here: the closing of the term and the query that
 * shows it check their own.
 */
final readonly class PendingGrades
{
    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private StudentMarksRepository $marks,
        private TeachingFacts $teaching,
        private EnrollmentFacts $enrollments,
    ) {}

    /**
     * @return list<PendingItem> By section, subject and activity.
     */
    public function of(int $schoolYearId, int $term): array
    {
        $items = [];

        foreach ($this->teaching->sectionsOf($schoolYearId) as $section) {
            $students = array_map(fn (RosterStudentFact $student) => $student->studentId, $this->enrollments->roster($schoolYearId, $section->id));

            if ($students === []) {
                continue;
            }

            $label = "{$section->gradeName} {$section->letter}";
            $found = match ($section->level) {
                'media_general' => $this->ofSubjects($schoolYearId, $term, $section, $label, $students),
                MarkKind::Literal->level() => $this->ofMarks($schoolYearId, $term, $section, $label, $students, MarkKind::Literal),
                MarkKind::Report->level() => $this->ofMarks($schoolYearId, $term, $section, $label, $students, MarkKind::Report),
                default => [],
            };
            array_push($items, ...$found);
        }

        return $items;
    }

    /**
     * @param  list<int>  $students
     * @return list<PendingItem>
     */
    private function ofSubjects(int $schoolYearId, int $term, SectionInfo $section, string $label, array $students): array
    {
        $names = $this->teaching->subjectNames($section->level);
        $periods = $this->enrollments->periods($schoolYearId, $students);
        $items = [];

        foreach ($this->teaching->curriculum($schoolYearId, $section->grade) as $subjectId) {
            $plan = $this->plans->planOf($schoolYearId, $section->id, $subjectId, $term);
            $subject = $names[$subjectId] ?? '';

            if ($plan === null) {
                $items[] = new PendingItem(PendingItem::NO_PLAN, $section->id, $label, $subjectId, $subject, null, $students);

                continue;
            }

            foreach ($plan->activities() as $activity) {
                $grades = $this->grades->forActivity($activity, $section->id);
                $missing = array_values(array_filter(
                    $students,
                    fn (int $student) => EnrollmentPeriod::applies($periods[$student] ?? [], $section->id, $activity->date) && $grades->markOf($student) === null,
                ));

                if ($missing !== []) {
                    $items[] = new PendingItem(PendingItem::ACTIVITY, $section->id, $label, $subjectId, $subject, $activity, $missing);
                }
            }
        }

        return $items;
    }

    /**
     * @param  list<int>  $students
     * @return list<PendingItem>
     */
    private function ofMarks(int $schoolYearId, int $term, SectionInfo $section, string $label, array $students, MarkKind $kind): array
    {
        $marks = $this->marks->forStudents($schoolYearId, $students);
        $missing = array_values(array_filter($students, fn (int $student) => $marks[$student]->isMissing((string) $term, $kind)));

        return $missing === [] ? [] : [new PendingItem($kind->value, $section->id, $label, null, null, null, $missing)];
    }
}
