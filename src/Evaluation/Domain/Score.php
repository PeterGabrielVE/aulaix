<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * A grade of Media General: from 1 to 20 with up to two decimals (EVA-R02.2),
 * kept in hundredths so its arithmetic is exact (EVA-NF03).
 */
final readonly class Score
{
    public const int MIN = 100;

    public const int MAX = 2000;

    public const int PASS = 1000;

    private function __construct(public int $hundredths) {}

    /**
     * @throws GradeNotValid
     */
    public static function of(string|int|float $input, string $field = 'grade'): self
    {
        $hundredths = Hundredths::parse($input);

        if ($hundredths === null || $hundredths < self::MIN || $hundredths > self::MAX) {
            throw GradeNotValid::outOfScale($field);
        }

        return new self($hundredths);
    }

    public static function fromHundredths(int $hundredths): self
    {
        return new self($hundredths);
    }

    public function display(): string
    {
        return Hundredths::display($this->hundredths);
    }
}
