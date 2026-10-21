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

    /** SPEC-007 CAL-R06: the school year and its terms. */
    case ManageCalendar = 'gestionar-calendario';

    /** SPEC-006 INS-R05: the institution's own data, type, levels, logo and signatures. */
    case ManageInstitution = 'gestionar-plantel';

    /** SPEC-006 INS-R05.3: seeing them, without the signatures. */
    case ViewInstitution = 'ver-plantel';

    /** SPEC-007 CAL-R05.2: grades of a closed term. Granted person by person, never by a base role (CAL-R05.4). */
    case EditClosedTermGrades = 'editar-notas-lapso-cerrado';
}
