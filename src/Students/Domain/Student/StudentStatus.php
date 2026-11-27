<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

enum StudentStatus: string
{
    case Active = 'active';
    case Withdrawn = 'withdrawn';
}
