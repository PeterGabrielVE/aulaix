<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * An activity as a form sends it; the plan checks it.
 */
final readonly class ActivityInput
{
    /**
     * @param  ?int  $id  Of an activity the plan already has, kept with its grades.
     * @param  ?string  $date  Y-m-d
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $date,
        public string|int|float|null $weight,
    ) {}
}
