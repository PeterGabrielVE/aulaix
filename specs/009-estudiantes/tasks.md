---
spec: 009-estudiantes
estado: implementada
fecha: 2026-10-21
diseño: design.md
depende-de: 001-tenancy (implementada), 003-catalogos (implementada), 004-identidad-global (implementada), 005-auditoria (implementada), ADR 0008 (aceptada)
---

# Tareas — Estudiantes y representantes

Regla transversal: al cerrar cada tarea, `php artisan test --compact`
(incluidos `--group=rls` y la suite `Architecture`) sigue en verde. TDD
(Q-02): primero el test en rojo, por el motivo correcto. Al cerrar una tarea,
sus tests se añaden a la matriz de `requirements.md`.

Tres PRs, cada uno usable por sí mismo:

- **PR A (fases 1 – 5):** ficha, identificación, representantes, salud,
  consulta y retiro.
- **PR B (fase 6):** traslados (ADR 0008).
- **PR C (fase 7):** cuenta del representante, correcciones y bloqueo de la
  eliminación de cuentas.

## PR A — Ficha del estudiante

### Fase 1 — Dominio (PHP puro)

- [x] **T-01** — `NationalId`, `SchoolId`, `StudentIdentification`,
  `Passport` y `GuardianDocument`: formato, normalización (puntos y
  guiones), `paddedNumber()` y las partes de la C.E.
  - Criterios: EST-R02.1, R02.2, R02.3, R03.2
  - Test: `tests/Unit/Students/Domain/IdentityTest.php`
  - Hecho cuando: `V11915432100` y `V32109876543` se aceptan con sus partes,
    y cada carácter inválido se rechaza con su mensaje.
- [x] **T-02** — `Student::register` y `update` con `PersonalData`:
  obligatorios reportados juntos, nacimiento no futuro (por `Clock`), país,
  estado, dirección y teléfono de emergencia opcionales.
  - Criterios: EST-R01.1 – R01.5
  - Test: `tests/Unit/Students/Domain/StudentTest.php`
- [x] **T-03** — Vínculos del estudiante (`Guardianship`, `Relationship`):
  al menos un legal, exactamente un principal; coherencia de la C.E.
  (año y C.I. de un representante); no desvincular al representante de la
  C.E.; paso de C.E. a C.I. con historial.
  - Criterios: EST-R02.4, R02.5, R02.7, R03.4 – R03.7
  - Test: `tests/Unit/Students/Domain/StudentGuardianshipTest.php`
- [x] **T-04** — `HealthRecord` y `changeHealth`; `withdraw` (fecha no
  futura, motivo de la lista, observación) y `reinstate` con historial de
  retiros.
  - Criterios: EST-R04.1, R06.1 – R06.4
  - Test: `tests/Unit/Students/Domain/StudentLifecycleTest.php`
- [x] **T-05** — Agregado `Guardian`: obligatorios, correo y dirección
  opcionales, `correct()`.
  - Criterios: EST-R03.1, R03.2, R03.8, R03.9
  - Test: `tests/Unit/Students/Domain/GuardianTest.php`

### Fase 2 — Catálogos y permiso

- [x] **T-06** — `Catalogs\Application\Countries` (ISO 3166-1, nombres en
  español) y `GeographyDirectory::stateExists`.
  - Criterios: EST-R01.4
  - Test: `tests/Unit/Catalogs/Application/CountriesTest.php`;
    `tests/Feature/Catalogs/GeographyDirectoryTest.php` (caso nuevo)
- [x] **T-07** — Permiso `gestionar-estudiantes` en `Permission` y en
  `BaseRole` (Administrador y Director). Migración de re-siembra en los
  planteles existentes, con `up` y `down` probados. Tabla de IAM-R11.1
  actualizada.
  - Criterios: EST-R09.2
  - Test: `tests/Feature/Students/StudentPermissionsTest.php`;
    `tests/Unit/IdentityAccess/Domain/Role/RoleSetTest.php`

### Fase 3 — Infraestructura

- [x] **T-08** — Migración de `students`, `student_previous_identifications`,
  `guardians`, `guardianships` y `student_withdrawals`:
  - `belongsToInstitution()` y `RowLevelSecurity::enable()`;
  - los `CHECK`, los índices únicos (identificación, documento, cuenta, un
    solo principal);
  - `up` y `down` probados.
  - Criterios: EST-R11.1
  - Test: `tests/Feature/Students/StudentIsolationTest.php` (grupo `rls`);
    `tests/Feature/TenantTableTest.php`
- [x] **T-09** — `EloquentStudentRepository` y `EloquentGuardianRepository`:
  - guardar la ficha entera (vínculos, retiros, C.E. anteriores) en una
    transacción;
  - `isIdentificationTaken`, `byDocument`;
  - índice único → `Conflict`.
  - Criterios: EST-R02.6, R03.3
  - Test: `tests/Feature/Students/Infrastructure/StudentRepositoryTest.php`

### Fase 4 — Casos de uso

- [x] **T-10** — Esqueleto del módulo:
  - `StudentsServiceProvider`;
  - puerto `StudentPermissions` y `GateStudentPermissions`;
  - `StudentAudit`;
  - `Students` en la lista de módulos de la constitución y en los tests de
    capas.
  - Criterios: EST-NF01
  - Test: `tests/Architecture/LayersTest.php`;
    `tests/Unit/Students/Application/StudentAuditTest.php`
- [x] **T-11** — `RegisterStudent` y `UpdateStudent`:
  - representantes nuevos y existentes (sin sobrescribir los existentes);
  - identificación única, con la sugerencia del tipo de parto;
  - permiso denegado → `AuthorizationDenied`;
  - bitácora con los valores anterior y nuevo, dentro de la transacción.
  - Criterios: EST-R01.1 – R01.5, R02.1 – R02.7, R03.1 – R03.7, R03.9,
    R09.1, R10.1
  - Test: `tests/Unit/Students/Application/RegisterStudentTest.php`
- [x] **T-12** — `ChangeHealth` y `UpdateGuardian`. La bitácora de salud
  registra solo los nombres de los campos.
  - Criterios: EST-R03.8, R04.1, R10.1, R10.3, NF02
  - Test: `tests/Unit/Students/Application/HealthAndGuardianTest.php`
- [x] **T-13** — `WithdrawStudent` y `ReinstateStudent`, con bitácora.
  - Criterios: EST-R06.1 – R06.4, R10.2
  - Test: `tests/Unit/Students/Application/WithdrawalTest.php`
- [x] **T-14** — Queries `ListStudents` (búsqueda por nombre, apellido,
  identificación y C.E. anterior; activos por defecto; paginada; `LIKE`
  escapado), `ShowStudent` (salud o solo el aviso) y `FindGuardian`.
  - Criterios: EST-R02.8, R03.3, R04.2, R04.3, R05.1 – R05.4, NF03
  - Test: `tests/Feature/Students/Infrastructure/StudentQueriesTest.php`
    (+ caso de 5 000 estudiantes en el grupo `perf`)

### Fase 5 — Entrada HTTP y pantallas

- [x] **T-15** — Entrada HTTP:
  - `StudentController` y `GuardianController`;
  - `StudentRequest`, `GuardianRequest` y `WithdrawalRequest`;
  - rutas con permisos y `throttle`;
  - `can.viewStudents` y `can.manageStudents` compartidos;
  - controladores en `MIGRATED_CONTROLLERS`.
  - Criterios: EST-R04.2, R05.1 – R05.4, R09.1, R09.3, R09.4, R11.2
  - Test: `tests/Feature/Students/StudentManagementTest.php`
- [x] **T-16** — Páginas `Students/{Index,Show,Form}.vue`:
  - lista con búsqueda;
  - ficha con el aviso médico;
  - formulario con los representantes y la búsqueda por documento;
  - retiro y reincorporación;
  - entrada «Estudiantes» del menú;
  - una columna en 360 px.
  - Criterios: EST-R05.1, NF05
  - Test: `StudentManagementTest` (props de las páginas);
    `tests/Architecture/TerminologyTest.php`

## PR B — Traslados (ADR 0008)

### Fase 6 — Traslados

- [x] **T-17** — Agregado `TransferRequest`: solicitar, aprobar, rechazar;
  caducada a los 15 días naturales (el día 15 aún vigente, el 16 no; por
  `Clock`).
  - Criterios: EST-R07.1, R07.5 – R07.7
  - Test: `tests/Unit/Students/Domain/TransferRequestTest.php`
- [x] **T-18** — Migración de `transfer_requests` con las políticas de ADR
  0008 (helper nuevo en `RowLevelSecurity`), con `up` y `down` probados.
  Tests de las políticas:
  - el destino no ve el origen;
  - un tercer plantel no ve nada;
  - solo el plantel del estudiante activo ve la solicitud pendiente;
  - un estudiante retirado no la hace visible.
  - Criterios: EST-R07.2, R07.4, R11.3
  - Test: `tests/Feature/Students/TransferPoliciesTest.php` (grupo `rls`)
- [x] **T-19** — Puerto `TransferCopier` y `TenantSwitchingTransferCopier`:
  - crea en el destino al estudiante y sus representantes, sin vínculo de
    cuenta, reutilizando los que el destino ya tenga;
  - vuelve al origen en un `finally`.
  - Test de arquitectura: `TenantActivator` solo aquí y en
    `LinkGuardianAccountHandler`.
  - Criterios: EST-R07.5, R07.8, R11.3
  - Test: `tests/Feature/Students/Infrastructure/TransferCopierTest.php`;
    `tests/Architecture/LayersTest.php`
- [x] **T-20** — `RequestTransfer` (rechaza si el destino ya tiene la
  identificación o una solicitud pendiente igual; misma respuesta exista o
  no), `ApproveTransfer` (aprobar, retirar por cambio de plantel y copiar en
  una transacción; todo o nada), `RejectTransfer` y `ListTransfers`, con
  bitácora en las dos puntas.
  - Criterios: EST-R07.1 – R07.8, R10.2
  - Test: `tests/Unit/Students/Application/TransferTest.php`
- [x] **T-21** — Entrada HTTP y pantalla:
  - `TransferController`, el limitador `transfer-requests` (20 por hora
    por plantel) y la página `Transfers/Index.vue`;
  - la entrada «Traslados» del menú;
  - de punta a punta entre dos planteles: respuesta idéntica, aprobación,
    rechazo y caducidad.
  - Criterios: EST-R07.1 – R07.7, R11.3, NF04
  - Test: `tests/Feature/Students/TransferFlowTest.php`

## PR C — Cuenta del representante

### Fase 7 — Representantes con cuenta y correcciones

- [x] **T-22** — Cambios en IdentityAccess:
  - `InviteMemberHandler` devuelve el id de la invitación;
  - `MemberDirectory::activeMemberByEmail`;
  - aceptar o unirse publica `MemberJoined` por el `EventBus` tras el
    commit.

  Los tests existentes de SPEC-004 pasan sin cambios en su lógica (Q-03).
  - Criterios: EST-R08.1 (prerrequisito)
  - Test: `tests/Feature/IdentityAccess/MemberJoinedTest.php`
- [x] **T-23** — `InviteGuardian` (siempre con el rol Representante; si el
  email ya es de un miembro, vínculo directo) y `LinkGuardianAccount`
  (escucha `MemberJoined`, activa ese plantel y vincula la cuenta de la
  invitación pendiente).
  - Criterios: EST-R08.1
  - Test: `tests/Unit/Students/Application/GuardianAccountTest.php`;
    `tests/Feature/Students/GuardianInvitationTest.php`
- [x] **T-24** — `GuardianStudentsQuery` y `GuardianPortalController`:
  - páginas `MyStudents/{Index,Show}.vue` sin salud;
  - 404 para un estudiante no representado;
  - `isGuardian` compartido y la entrada «Mis representados» del menú.
  - Criterios: EST-R08.2, R08.3
  - Test: `tests/Feature/Students/GuardianPortalTest.php`
- [x] **T-25** — Correcciones:
  - el agregado `CorrectionRequest`;
  - migración de `correction_requests` (RLS, `up` y `down` probados);
  - `RequestCorrection`, `ResolveCorrection` y `ListCorrections`;
  - `CorrectionController` y la página `Corrections/Index.vue`.
  - Criterios: EST-R08.4, R08.5
  - Test: `tests/Unit/Students/Domain/CorrectionRequestTest.php`;
    `tests/Feature/Students/CorrectionTest.php`
- [x] **T-26** — `SoleLegalGuardianBlocker`, etiquetado como
  `DeletionBlocker`: recorre los planteles de la cuenta y bloquea si es el
  único representante legal de un estudiante activo. Actualiza la matriz de
  IDG-R08.3.
  - Criterios: EST-R08.6
  - Test: `tests/Feature/Students/AccountDeletionBlockTest.php`

## Verificación final

- [x] `php artisan test --compact` en verde (incluidos `--group=rls` y `--group=perf`).
- [x] Tests de arquitectura en verde.
- [x] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [x] `migrate:fresh --seed` dos veces y rollback de las migraciones nuevas.
- [x] Matriz de trazabilidad completa.
- [ ] `/security-review` de cada PR (datos de menores, traslados y acceso del representante; S-12).
- [x] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| EST-R01 | T-02, T-06, T-11 |
| EST-R02 | T-01, T-03, T-09, T-11, T-14 |
| EST-R03 | T-01, T-03, T-05, T-09, T-11, T-12, T-14 |
| EST-R04 | T-04, T-12, T-14, T-15 |
| EST-R05 | T-14, T-15, T-16 |
| EST-R06 | T-04, T-13 |
| EST-R07 | T-17 – T-21 |
| EST-R08 | T-22 – T-26 |
| EST-R09 | T-07, T-11, T-15 |
| EST-R10 | T-11, T-12, T-13, T-20 |
| EST-R11 | T-08, T-15, T-18, T-19, T-21 |
| EST-NF01 – NF05 | T-10, T-12, T-14, T-21, T-16 |
