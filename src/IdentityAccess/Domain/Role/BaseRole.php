<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Role;

/**
 * The six roles every institution has (IAM-R11.1). The order of the cases is
 * their priority: a user holding several lands on the home screen of the
 * first one (IAM-R12.2).
 */
enum BaseRole: string
{
    case Administrador = 'Administrador';
    case Director = 'Director';
    case Coordinador = 'Coordinador';
    case Docente = 'Docente';
    case Representante = 'Representante';
    case Estudiante = 'Estudiante';

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrador => Permission::cases(),
            self::Director => [Permission::ManageUsers, Permission::ViewStudents, Permission::ViewGrades, Permission::ViewAuditLog],
            self::Coordinador, self::Docente => [Permission::ViewStudents, Permission::ManageGrades, Permission::ViewGrades],
            self::Representante, self::Estudiante => [Permission::ViewGrades],
        };
    }

    public function homeScreen(): HomeScreen
    {
        return match ($this) {
            self::Administrador => HomeScreen::Admin,
            self::Director => HomeScreen::Director,
            self::Coordinador => HomeScreen::Coordinator,
            self::Docente => HomeScreen::Teacher,
            self::Representante => HomeScreen::Guardian,
            self::Estudiante => HomeScreen::Student,
        };
    }
}
