<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Correction;

/**
 * A correction request as the managers and its guardian see it.
 */
final readonly class CorrectionView
{
    /**
     * @param  ?string  $studentName  Null when it is about the guardian's own data.
     * @param  string  $status  pending or resolved.
     */
    public function __construct(
        public int $id,
        public string $guardianName,
        public ?string $studentName,
        public string $text,
        public string $status,
        public string $submittedOn,
    ) {}
}
