<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Role;

/**
 * The landing screen of each base role (IAM-R12). Which route shows it is a
 * presentation concern, mapped in app/.
 */
enum HomeScreen: string
{
    case Admin = 'admin';
    case Director = 'director';
    case Coordinator = 'coordinator';
    case Teacher = 'teacher';
    case Guardian = 'guardian';
    case Student = 'student';
}
