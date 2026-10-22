---
spec: 008-estructura-academica
estado: implementando
fecha: 2026-10-22
diseño: design.md
depende-de: 003-catalogos (implementada), 004-identidad-global (implementada), 005-auditoria (implementada), 006-institucion (implementada), 007-anio-escolar (implementada)
---

# Tareas — Estructura académica

Regla transversal: al cerrar cada tarea, `php artisan test --compact`
(incluidos `--group=rls` y la suite `Architecture`) sigue en verde. TDD
(Q-02): primero el test en rojo, por el motivo correcto. Al cerrar una tarea,
sus tests se añaden a la matriz de `requirements.md`.

Dos PRs, cada uno usable por sí mismo:

- **PR A (fases 1 – 5):** grados, secciones, pensum y copia del año
  anterior.
- **PR B (fases 6 – 7):** asignaciones de personas, «Mis asignaciones», la
  consulta para las notas y el bloqueo de eliminación de cuenta.

## PR A — Secciones y pensum

### Fase 1 — Dominio (PHP puro)

- [x] **T-01** — `Grade` (17 grados con nivel, etapa, orden y tope) y
  `Stage`; `SectionLetter` y `Capacity` (tope por etapa, motivo para
  superarlo).
  - Criterios: EAC-R01.1, R02.3, R02.4, R02.8, R02.9
  - Test: `tests/Unit/AcademicStructure/Domain/GradeAndSectionTest.php`
- [x] **T-02** — Agregado `SchoolYearStructure`:
  - secciones: crear, letra única por grado, cupo, quitar con sus
    asignaciones;
  - pensum: materias del nivel del grado, sin repetir, quitar en cascada;
  - grados limitados a los niveles del plantel;
  - reglas por estado del año.

  Las preguntas de «en uso» llegan resueltas del caso de uso.
  - Criterios: EAC-R01.2, R02.1, R02.2, R02.5, R02.7, R03.1 – R03.4,
    R07.1 – R07.4
  - Test: `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php`
- [x] **T-03** — `copyFrom`: solo a un año planificado y vacío, secciones y
  pensum sin personas, solo los grados que el plantel ofrece hoy.
  - Criterios: EAC-R04.1 – R04.3
  - Test: `tests/Unit/AcademicStructure/Domain/CopyStructureTest.php`

### Fase 2 — Consultas de otros módulos y permisos

- [x] **T-04** — Consultas públicas nuevas:
  - `Catalogs\Application\Subjects` (`levelOf`, `ofLevel`);
  - `AcademicCalendar\Application\SchoolYearLookup` (`statusOf`,
    `activeYear`, `previousOf`).
  - Criterios: EAC-R03.2, R04.1, R07.1 – R07.4 (prerrequisitos)
  - Test: `tests/Feature/Catalogs/SubjectsTest.php`;
    `tests/Unit/AcademicCalendar/Application/SchoolYearLookupTest.php`
- [x] **T-05** — Permisos `gestionar-estructura` (Administrador, Director) y
  `ver-estructura` (Administrador, Director, Coordinador, Docente) en
  `Permission` y `BaseRole`. Migración de re-siembra con `up` y `down`
  probados. Tabla de IAM-R11.1 actualizada.
  - Criterios: EAC-R10.2, R10.3
  - Test: `tests/Feature/AcademicStructure/StructurePermissionsTest.php`;
    `tests/Unit/IdentityAccess/Domain/Role/RoleSetTest.php`

### Fase 3 — Infraestructura

- [x] **T-06** — Migración de `sections`, `curriculum_subjects`,
  `teaching_assignments` y `coordinator_assignments`:
  - RLS, `CHECK` y los índices únicos;
  - `null on delete` en las cuentas de las asignaciones;
  - `up` y `down` probados.
  - Criterios: EAC-R12.1
  - Test: `tests/Feature/AcademicStructure/StructureIsolationTest.php` (grupo
    `rls`); `tests/Feature/TenantTableTest.php`
- [x] **T-07** — `EloquentSchoolYearStructureRepository`: el año entero de
  ida y vuelta en una transacción; índice único → `Conflict`.
  - Criterios: EAC-R02.2, R03.3
  - Test: `tests/Feature/AcademicStructure/Infrastructure/StructureRepositoryTest.php`

### Fase 4 — Casos de uso

- [x] **T-08** — Esqueleto del módulo:
  - `AcademicStructureServiceProvider`;
  - `StructurePermissions` y `GateStructurePermissions`;
  - `StructureAudit`;
  - `RemovalGuard` como punto de extensión etiquetado, sin implementadores.
  - Criterios: EAC-NF01, R11.1
  - Test: `tests/Unit/AcademicStructure/Application/StructureAuditTest.php`;
    `tests/Architecture/LayersTest.php`
- [x] **T-09** — `CreateSection`, `ChangeCapacity` y `RemoveSection`:
  - con el bloqueo del calendario, los niveles del plantel (Tenancy) y el
    estado del año;
  - el `RemovalGuard` decide (en memoria: «en uso»);
  - bitácora dentro de la transacción y permisos.
  - Criterios: EAC-R01.2, R01.3, R02.1 – R02.9, R07.1 – R07.4, R10.1, R11.1
  - Test: `tests/Unit/AcademicStructure/Application/SectionsTest.php`
- [x] **T-10** — `SetCurriculum` (materias del catálogo por nivel) y
  `CopyStructure` (del año anterior o de uno elegido), con bitácora.
  - Criterios: EAC-R03.1 – R03.4, R04.1 – R04.3, R07.3, R11.1
  - Test: `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php`
- [x] **T-11** — Queries `ShowStructure` (grados, secciones, cupos con
  motivo, pensum) y `SectionsOfYear` (pública, para SPEC-010).
  - Criterios: EAC-R08.1, R08.3, NF03
  - Test: `tests/Feature/AcademicStructure/Infrastructure/StructureQueriesTest.php`
    (+ caso de 60 secciones en el grupo `perf`)

### Fase 5 — Entrada HTTP y pantalla

- [x] **T-12** — Entrada HTTP:
  - `StructureController` y `SectionController` con sus FormRequests;
  - rutas con permisos y `throttle`;
  - `can.viewStructure` y `can.manageStructure` compartidos;
  - controladores en `MIGRATED_CONTROLLERS`.
  - Criterios: EAC-R08.1, R10.1, R10.4, R12.2
  - Test: `tests/Feature/AcademicStructure/StructureManagementTest.php`
- [x] **T-13** — Página `Structure/Index.vue`:
  - selector de año y grados del plantel;
  - secciones con su cupo (y el motivo si supera el tope);
  - pensum por grado y copia del año anterior;
  - entrada «Estructura» del menú;
  - una columna en 360 px.
  - Criterios: EAC-R08.1, NF04
  - Test: `StructureManagementTest` (props de la página);
    `tests/Architecture/TerminologyTest.php`

## PR B — Asignaciones

### Fase 6 — Dominio, personas y casos de uso

- [ ] **T-14** — Asignaciones en el agregado:
  - docente por materia del pensum en una sección (uno por materia,
    reemplazo, quitar; en todos los niveles);
  - docente guía;
  - coordinadores por nivel o por grados del nivel, varios por nivel.
  - Criterios: EAC-R05.1, R05.3 – R05.5, R05.7, R06.1, R06.3, R06.5, R06.6
  - Test: `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php`
- [ ] **T-15** — `MemberDirectory::activeMembersWithRole` (IdentityAccess) y
  el puerto `StaffDirectory` con `IdentityAccessStaffDirectory`: miembro
  activo con un rol, por cualquiera de sus roles (un Director con rol
  Docente cuenta).
  - Criterios: EAC-R05.2, R06.2, R06.4, R12.3
  - Test: `tests/Feature/AcademicStructure/Infrastructure/StaffDirectoryTest.php`
- [ ] **T-16** — `AssignTeacher`, `SetGuide`, `AssignCoordinator` y
  `RemoveCoordinator`, con `StaffDirectory`, bloqueo, estado del año,
  bitácora (anterior y nuevo) y permisos.
  - Criterios: EAC-R05.1 – R05.5, R05.7, R06.1 – R06.6, R07.2, R11.1, R12.3
  - Test: `tests/Unit/AcademicStructure/Application/AssignmentsTest.php`
- [ ] **T-17** — Consultas:
  - `ShowStructure` muestra las personas asignadas y marca las que ya no
    son miembros activos; sus asignaciones se conservan;
  - `MyAssignments`;
  - `TeachingQuery` (`teaches`, `sectionsOf`), en menos de 10 ms.
  - Criterios: EAC-R05.6, R08.2, R08.4, R08.5, NF02
  - Test: `tests/Feature/AcademicStructure/Infrastructure/AssignmentQueriesTest.php`
    (+ caso del grupo `perf`)
- [ ] **T-18** — `ActiveAssignmentBlocker`, etiquetado como
  `DeletionBlocker`: dicta o guía en el año activo de algún plantel.
  Actualiza la matriz de IDG-R08.4.
  - Criterios: EAC-R09.1
  - Test: `tests/Feature/AcademicStructure/AccountDeletionBlockTest.php`

### Fase 7 — Entrada HTTP y pantallas

- [ ] **T-19** — Entrada HTTP y pantallas:
  - `AssignmentController` (docente, guía, coordinadores) y
    `MyAssignmentsController`;
  - en `Structure/Index.vue`, los selectores de personas y el aviso de las
    que ya no son miembros;
  - la página `MyAssignments/Index.vue` y la entrada «Mis asignaciones» del
    menú.
  - Criterios: EAC-R05.1, R06.1, R06.3, R08.2, R08.5, R10.4
  - Test: `tests/Feature/AcademicStructure/AssignmentManagementTest.php`

## Verificación final

- [ ] `php artisan test --compact` en verde (incluidos `--group=rls` y `--group=perf`).
- [ ] Tests de arquitectura en verde.
- [ ] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [ ] `migrate:fresh --seed` dos veces y rollback de las migraciones nuevas.
- [ ] Matriz de trazabilidad completa.
- [ ] `/security-review` de cada PR (permisos y asignaciones que habilitarán la carga de notas; S-12).
- [ ] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| EAC-R01 | T-01, T-02, T-09 |
| EAC-R02 | T-01, T-02, T-07, T-09 |
| EAC-R03 | T-02, T-04, T-07, T-10 |
| EAC-R04 | T-03, T-10 |
| EAC-R05 | T-14, T-15, T-16, T-17, T-19 |
| EAC-R06 | T-14, T-15, T-16, T-19 |
| EAC-R07 | T-02, T-09, T-10, T-16 |
| EAC-R08 | T-11, T-12, T-13, T-17, T-19 |
| EAC-R09 | T-18 |
| EAC-R10 | T-05, T-09, T-12, T-19 |
| EAC-R11 | T-08, T-09, T-10, T-16 |
| EAC-R12 | T-06, T-12, T-15, T-16 |
| EAC-NF01 – NF04 | T-08, T-17, T-11, T-13 |
