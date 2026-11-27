---
spec: 007-anio-escolar
estado: implementada
fecha: 2026-10-19
diseño: design.md
depende-de: 001-tenancy (implementada), 004-identidad-global (implementada), 005-auditoria (implementada)
---

# Tareas — Año escolar y lapsos

Regla transversal: al cerrar cada tarea, `php artisan test --compact`
(incluidos `--group=rls` y la suite `Architecture`) sigue en verde. TDD
(Q-02): primero el test en rojo, por el motivo correcto.

## Fase 1 — Dominio (PHP puro)

- [x] **T-01** — `DateRange` (fechas sin hora; inicio ≤ fin; `contains`,
  `overlaps`, `startsAfter`) y `TermNumber` (1..3).
  - Criterios: CAL-R01.2, R01.3 (base)
  - Test: `tests/Unit/AcademicCalendar/Domain/DateRangeTest.php`
- [x] **T-02** — Agregado `SchoolYear` con sus `Term`: `plan()` con las
  validaciones de fechas, duración ≤ 11 meses y nombre derivado.
  - Criterios: CAL-R01.1 – R01.5, R01.7, R01.8
  - Test: `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php`
- [x] **T-03** — Transiciones del año: `activate()`, `close(confirmed)`,
  `canBeDeleted()`; sin transiciones en un año cerrado.
  - Criterios: CAL-R02.2, R02.4 – R02.6, R09.2
  - Test: `SchoolYearTest` (transiciones del año)
- [x] **T-04** — Lapsos: `closeTerm(confirmed)`,
  `reopenLastClosedTerm(reason)`, invariante de un solo lapso abierto; nada
  depende de la fecha de hoy.
  - Criterios: CAL-R04.1 – R04.7
  - Test: `SchoolYearTest` (lapsos)
- [x] **T-05** — `changeDates(...)`: re-valida todo, rechaza en un año
  cerrado o en un lapso cerrado; el nombre sigue a las fechas.
  - Criterios: CAL-R03.1 – R03.4
  - Test: `SchoolYearTest` (fechas)
- [x] **T-06** — Servicio de dominio `SchoolYearSchedule`: un planificado,
  un activo, sin solape con otros años de cualquier estado.
  - Criterios: CAL-R01.6, R01.9, R02.1, R02.3
  - Test: `tests/Unit/AcademicCalendar/Domain/SchoolYearScheduleTest.php`
- [x] **T-07** — Regla de bloqueo de notas (`allowsGradeChanges`).
  - Criterios: CAL-R05.1 – R05.3
  - Test: `tests/Unit/AcademicCalendar/Domain/TermEditabilityTest.php`

## Fase 2 — Permisos (IdentityAccess)

- [x] **T-08** — `Permission::ManageCalendar` y `EditClosedTermGrades`;
  `BaseRole`: Administrador con todos salvo `EditClosedTermGrades`, Director
  con `ManageCalendar`. `RolePermissionSeeder` lee de `BaseRole`. Migración
  que vuelve a sembrar los planteles existentes (con `down()`). Actualizar
  la tabla de IAM-R11.1 en SPEC-002.
  - Criterios: CAL-R05.4, R06.5
  - Test: `tests/Unit/IdentityAccess/Domain/Role/RoleSetTest.php` (permisos por rol);
    `tests/Feature/AcademicCalendar/CalendarPermissionsTest.php` (planteles existentes)
- [x] **T-09** — `SetClosedTermGradeEditing` (solo un Administrador lo
  concede o retira; permiso directo en el plantel activo; auditado) y la
  casilla en la edición del miembro. El permiso se retira al salir del
  plantel o al cerrar la cuenta.
  - Criterios: CAL-R05.4
  - Test: `tests/Feature/AcademicCalendar/ClosedTermPermissionTest.php`

## Fase 3 — Datos

- [x] **T-10** — Migraciones de `school_years` y `terms`:
  `belongsToInstitution` + `RowLevelSecurity::enable`, `CHECK`, índices
  únicos parciales y `EXCLUDE` con `btree_gist` (ADR 0007).
  - Criterios: CAL-R08.2, NF02
  - Test: `TenantTableTest` (automático); `tests/Feature/AcademicCalendar/CalendarIsolationTest.php`
    (grupo `rls`); `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php`
    (cada restricción con SQL directo)
- [x] **T-11** — `EloquentSchoolYearRepository` + mapper (ida y vuelta con
  sus lapsos), `DatabaseCalendarLock` y el service provider.
  - Criterios: CAL-NF02
  - Test: `tests/Feature/AcademicCalendar/Infrastructure/EloquentSchoolYearRepositoryTest.php`

## Fase 4 — Casos de uso

- [x] **T-12** — `CreateSchoolYear`, `ChangeSchoolYearDates` y
  `DeleteSchoolYear`, con bloqueo, transacción y bitácora.
  - Criterios: CAL-R01.*, R03.*, R09.*, R07.1
  - Test: `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php`
    (repositorio en memoria, `AuditTrail` falso)
- [x] **T-13** — `ActivateSchoolYear`, `CloseSchoolYear`, `CloseTerm` y
  `ReopenTerm`.
  - Criterios: CAL-R02.*, R04.*, R06.2, R07.1
  - Test: `tests/Unit/AcademicCalendar/Application/SchoolYearLifecycleTest.php`
- [x] **T-14** — Queries `ListSchoolYears` y `TermEditability` (API para
  SPEC-011).
  - Criterios: CAL-R05.1 – R05.3, R06.1, NF03
  - Test: `tests/Feature/AcademicCalendar/Infrastructure/TermEditabilityTest.php`
    (+ caso de rendimiento en el grupo `perf`)

## Fase 5 — Entrada HTTP y pantalla

- [x] **T-15** — `SchoolYearController` y `TermController`, FormRequests,
  rutas con `permission:gestionar-calendario` y `throttle`, `can.manageCalendar`
  compartido. Ambos en `MIGRATED_CONTROLLERS`.
  - Criterios: CAL-R06.1 – R06.4, R08.1, R08.3, NF01
  - Test: `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php`
- [x] **T-16** — `Pages/Calendar/Index.vue` (crear y editar el año, activar,
  cerrar lapso y año con confirmación, reabrir con motivo, eliminar el
  planificado) y la entrada del menú.
  - Criterios: CAL-R06.1, R06.2, NF04
  - Test: `SchoolYearManagementTest` (props de la página); `TerminologyTest`
- [x] **T-17** — Flujo completo de punta a punta: crear → activar → cerrar
  los 3 lapsos → reabrir el último → cerrarlo → cerrar el año; y la
  respuesta 409 ante una violación de restricción.
  - Criterios: CAL-R02, R04, NF02
  - Test: `SchoolYearManagementTest` (flujo y conflicto)

## Verificación final

- [x] `php artisan test --compact` en verde (incluidos `--group=rls` y `--group=perf`).
- [x] Tests de arquitectura en verde.
- [x] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [x] `migrate:fresh --seed` dos veces y rollback de las migraciones nuevas.
- [ ] Disponibilidad de `btree_gist` comprobada en el servidor de producción (ADR 0007).
- [x] Matriz de trazabilidad completa.
- [x] `/security-review` (toca permisos, S-12): sin hallazgos de confianza alta o media (2026-10-23; ver «Revisión de seguridad» en `specs/roadmap.md`).
- [x] `estado` de requirements/design/tasks actualizado.
