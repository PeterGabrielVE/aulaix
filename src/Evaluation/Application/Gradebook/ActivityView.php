<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Gradebook;

final readonly class ActivityView
{
    /**
     * @param  string  $date  Y-m-d
     * @param  string  $weight  "33,33"
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $date,
        public string $weight,
    ) {}
}
