<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The final grade of a subject (EVA-R03.3): the average of its term grades,
 * rounded half up; incomplete while a term is (EVA-R03.4); a term that does
 * not apply is left out (EVA-R03.7). Failed below 10 (EVA-R03.5).
 */
final readonly class FinalScore
{
    public const int PASS = 10;

    private function __construct(
        public string $state,
        public ?int $score,
    ) {}

    /**
     * @param  list<TermResult>  $terms
     */
    public static function of(array $terms): self
    {
        $scores = [];

        foreach ($terms as $term) {
            if ($term->isIncomplete()) {
                return new self('incomplete', null);
            }

            if ($term->isScored()) {
                $scores[] = (int) $term->score;
            }
        }

        return $scores === []
            ? new self('not_applicable', null)
            : new self('scored', Rounding::nearest(array_sum($scores), count($scores)));
    }

    public function isComplete(): bool
    {
        return $this->state === 'scored';
    }

    public function failed(): bool
    {
        return $this->score !== null && $this->score < self::PASS;
    }
}
