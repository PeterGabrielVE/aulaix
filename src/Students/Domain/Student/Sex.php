<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

enum Sex: string
{
    case Female = 'female';
    case Male = 'male';
}
