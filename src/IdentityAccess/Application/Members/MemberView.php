<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

final readonly class MemberView
{
    /**
     * @param  list<string>  $roles  In priority order (IAM-R12.2).
     * @param  bool  $active  False when the membership is suspended.
     * @param  bool  $canEditClosedTermGrades  Holds the permission granted person by person (CAL-R05.4).
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $roles,
        public bool $active,
        public bool $canEditClosedTermGrades = false,
    ) {}
}
