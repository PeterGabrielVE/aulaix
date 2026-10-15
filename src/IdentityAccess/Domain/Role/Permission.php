<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Role;

enum Permission: string
{
    case ManageUsers = 'gestionar-usuarios';
    case ManageRoles = 'gestionar-roles';
    case ViewStudents = 'ver-estudiantes';
    case ManageGrades = 'gestionar-calificaciones';
    case ViewGrades = 'ver-calificaciones';
    case ViewAuditLog = 'ver-bitacora';
}
