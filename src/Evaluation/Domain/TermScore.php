<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The grade of a term (EVA-R03.1): the grades of the activities that apply,
 * weighed by their share (NP as 1), over the shares that apply, so an NA
 * spreads the 100 % among the rest (EVA-R03.6); rounded to the whole
 * number, ,5 up, with exact integers (EVA-NF03).
 */
final class TermScore
{
    /**
     * @param  list<ScoredActivity>  $activities
     */
    public static function of(array $activities): TermResult
    {
        $weighed = 0;
        $weights = 0;

        foreach ($activities as $activity) {
            if (! $activity->applies) {
                continue;
            }

            if ($activity->mark === null) {
                return TermResult::incomplete();
            }

            $weighed += $activity->mark->value() * $activity->weight->hundredths;
            $weights += $activity->weight->hundredths;
        }

        if ($weights === 0) {
            return TermResult::notApplicable();
        }

        // Grades in hundredths: Σ(grade × weight) / (Σ weight × 100) is the whole-number grade.
        return TermResult::scored(Rounding::nearest($weighed, $weights * 100));
    }
}
