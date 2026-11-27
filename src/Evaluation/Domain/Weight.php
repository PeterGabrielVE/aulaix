<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use InvalidArgumentException;

/**
 * The share of an activity in its term, a percentage with up to two
 * decimals, kept in hundredths (EVA-R01.1): more than 0, up to 100.
 */
final readonly class Weight
{
    public const int WHOLE = 10000;

    private function __construct(public int $hundredths) {}

    public static function of(string|int|float $input): self
    {
        $hundredths = Hundredths::parse($input);

        if ($hundredths === null || $hundredths <= 0 || $hundredths > self::WHOLE) {
            throw new InvalidArgumentException('A weight is a percentage above 0 and up to 100, with up to two decimals.');
        }

        return new self($hundredths);
    }

    /**
     * A stored weight, already checked.
     */
    public static function fromHundredths(int $hundredths): self
    {
        return new self($hundredths);
    }

    public function display(): string
    {
        return Hundredths::display($this->hundredths);
    }
}
