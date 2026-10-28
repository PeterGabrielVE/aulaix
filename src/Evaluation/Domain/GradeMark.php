<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * What a student got in an activity: a grade, or «no presentó» (NP), which
 * counts as the lowest grade in the arithmetic (EVA-R02.3).
 */
final readonly class GradeMark
{
    private function __construct(private ?Score $score) {}

    public static function score(Score $score): self
    {
        return new self($score);
    }

    public static function notPresented(): self
    {
        return new self(null);
    }

    /**
     * "NP" for «no presentó», a grade, or null for no grade (EVA-R02.4).
     *
     * @throws GradeNotValid
     */
    public static function fromInput(string|int|float|null $input, string $field = 'grade'): ?self
    {
        $text = trim((string) $input);

        return match (true) {
            $text === '' => null,
            strtoupper($text) === 'NP' => self::notPresented(),
            default => self::score(Score::of($text, $field)),
        };
    }

    public function isNotPresented(): bool
    {
        return $this->score === null;
    }

    /**
     * In hundredths, for the arithmetic.
     */
    public function value(): int
    {
        return $this->score->hundredths ?? Score::MIN;
    }

    public function scoreOrNull(): ?Score
    {
        return $this->score;
    }
}
