<?php

declare(strict_types=1);

namespace AulaX\Students\Application\StudentForm;

use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\PersonalData;

/**
 * The student form, checked and with its guardians saved.
 */
final readonly class PreparedStudent
{
    /**
     * @param  list<Guardianship>  $guardianships
     */
    public function __construct(
        public PersonalData $personalData,
        public StudentIdentification $identification,
        public array $guardianships,
    ) {}
}
