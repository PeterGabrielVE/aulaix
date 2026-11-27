<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Enrollable;

/**
 * What enrolling a student needs to know of it (SPEC-010): never its health
 * data.
 */
final readonly class EnrollableStudent
{
    /**
     * @param  string  $birthDate  Y-m-d
     */
    public function __construct(
        public int $id,
        public string $fullName,
        public string $identification,
        public string $birthDate,
        public bool $active,
    ) {}
}
