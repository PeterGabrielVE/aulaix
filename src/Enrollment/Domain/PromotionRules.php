<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * The rules of promotion by level (MAT-R06.3 – R06.7), the grade a result
 * leads to (MAT-R07.1) and graduation (MAT-R15.1, R15.2). Until SPEC-011
 * proposes the result from the grades, a manager gives it and these rules
 * limit what they may choose.
 */
final class PromotionRules
{
    public const string LAST_GRADE = 'media_5';

    /**
     * @param  list<int>  $curriculum  Subject ids of the grade that year.
     *
     * @throws ResultNotAllowed
     */
    public static function check(Level $level, PromotionResult $result, array $curriculum): void
    {
        if ($level === Level::Initial && $result->outcome !== Outcome::Promoted) {
            throw ResultNotAllowed::outcome('En Educación Inicial el estudiante siempre es promovido.');
        }

        if ($level === Level::Primary && $result->outcome === Outcome::PromotedWithPending) {
            throw ResultNotAllowed::outcome('En Primaria no hay materias pendientes: el estudiante es promovido o repite.');
        }

        if (array_diff($result->pendingSubjects, $curriculum) !== []) {
            throw ResultNotAllowed::pendingSubjects('Las materias pendientes deben ser del pensum del grado.');
        }
    }

    /**
     * The results a manager may choose for a grade of the level (MAT-R06.3,
     * R06.4).
     *
     * @return list<Outcome>
     */
    public static function allowedOutcomes(Level $level): array
    {
        return match ($level) {
            Level::Initial => [Outcome::Promoted],
            Level::Primary => [Outcome::Promoted, Outcome::Repeats, Outcome::LostByAbsence],
            Level::Secondary => [Outcome::Promoted, Outcome::PromotedWithPending, Outcome::Repeats, Outcome::LostByAbsence],
        };
    }

    /**
     * The grade the student goes on to: the next one when promoted, the
     * same one otherwise; null after the last grade.
     *
     * @param  list<string>  $gradesInOrder
     */
    public static function nextGrade(string $grade, Outcome $outcome, array $gradesInOrder): ?string
    {
        if (! $outcome->isPromotion()) {
            return $grade;
        }

        $position = array_search($grade, $gradesInOrder, true);

        return $position === false ? null : ($gradesInOrder[$position + 1] ?? null);
    }

    public static function graduates(string $grade, PromotionResult $result): bool
    {
        return $grade === self::LAST_GRADE && $result->outcome === Outcome::Promoted;
    }

    public static function waitsToGraduate(string $grade, PromotionResult $result): bool
    {
        return $grade === self::LAST_GRADE && $result->outcome === Outcome::PromotedWithPending;
    }
}
