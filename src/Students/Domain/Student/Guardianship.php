<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Identity\GuardianDocument;

/**
 * A guardian of the student (EST-R03.4): how they are related, whether they
 * are a legal guardian and whether they are the primary contact. Carries the
 * guardian's document, which a cédula escolar must match (EST-R02.5).
 */
final readonly class Guardianship
{
    public function __construct(
        public GuardianId $guardianId,
        public GuardianDocument $document,
        public Relationship $relationship,
        public bool $isLegal,
        public bool $isPrimary,
    ) {}
}
