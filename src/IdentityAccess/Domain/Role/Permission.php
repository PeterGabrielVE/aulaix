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

    /** SPEC-009 EST-R09: registering, editing, withdrawing and transferring students, and their guardians. */
    case ManageStudents = 'gestionar-estudiantes';

    /** SPEC-008 EAC-R10: sections, curriculum and who teaches, guides and coordinates. */
    case ManageStructure = 'gestionar-estructura';

    /** SPEC-008 EAC-R08.1: seeing that structure. */
    case ViewStructure = 'ver-estructura';

    /** SPEC-007 CAL-R05.2: grades of a closed term. Granted person by person, never by a base role (CAL-R05.4). */
    case EditClosedTermGrades = 'editar-notas-lapso-cerrado';
}
