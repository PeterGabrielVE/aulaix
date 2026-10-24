<?php

declare(strict_types=1);

namespace AulaX\Workspace\Domain;

/**
 * The six home screens (IAM-R12). Same values as IdentityAccess's
 * HomeScreen, which is not reachable from here (A-06).
 */
enum HomeKind: string
{
    case Admin = 'admin';
    case Director = 'director';
    case Coordinator = 'coordinator';
    case Teacher = 'teacher';
    case Guardian = 'guardian';
    case Student = 'student';

    /**
     * The administration of the institution: Administrador and Director.
     */
    public function isManagement(): bool
    {
        return $this === self::Admin || $this === self::Director;
    }
}
