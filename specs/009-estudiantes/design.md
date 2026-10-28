---
spec: 009-estudiantes
estado: implementada
fecha: 2026-10-21
requisitos: requirements.md
depende-de: 001-tenancy, 003-catalogos, 004-identidad-global, 005-auditoria
adr: 0008-traslados-entre-planteles (aceptada)
---

# Diseño — Estudiantes y representantes

## Resumen

Un módulo nuevo, `Students`, con tres agregados: `Student` (ficha,
identificación, salud, vínculos con representantes, retiros), `Guardian`
(representante del plantel, compartido entre hermanos) y `TransferRequest`.
Todas las tablas son del plantel, con RLS (S-01). El traslado es la única
excepción controlada: `transfer_requests` tiene políticas propias y la
aprobación copia la ficha al destino en la misma transacción (ADR 0008).

Toca otros módulos solo por su capa `Application`:

- `Catalogs`: geografía y una lista de países nueva;
- `Tenancy`: plantel activo y activador;
- `IdentityAccess`: invitar, saber qué cuenta se unió y el bloqueo de
  eliminación de IDG-R08.3;
- `Audit`: la bitácora.

## Módulo y capas

```
src/Students/
├── Domain/
│   ├── Student/
│   │   ├── Student.php                 agregado: ficha, identificación, salud, vínculos, retiros
│   │   ├── StudentId.php · StudentStatus.php (active | withdrawn) · Sex.php
│   │   ├── PersonalData.php            VO: nombres, apellidos, sexo, nacimiento, nacionalidad, lugar, dirección, teléfono de emergencia
│   │   ├── HealthRecord.php            VO: alergias, condiciones, discapacidad (opcionales)
│   │   ├── Guardianship.php            VO: representante, parentesco, legal, contacto principal, su documento
│   │   ├── Relationship.php            enum: mother | father | other
│   │   ├── Withdrawal.php · WithdrawalReason.php (enum de 4 motivos)
│   │   └── StudentRepository.php       puerto
│   ├── Identity/
│   │   ├── NationalId.php              VO: C.I. (V|E + 1–8 dígitos)
│   │   ├── SchoolId.php                VO: C.E. de 12 caracteres y sus partes
│   │   ├── StudentIdentification.php   VO: NationalId o SchoolId
│   │   ├── Passport.php · GuardianDocument.php (NationalId o Passport)
│   ├── Guardian/
│   │   ├── Guardian.php                agregado: datos del representante, cuenta vinculada
│   │   └── GuardianRepository.php      puerto
│   ├── Transfer/
│   │   ├── TransferRequest.php         agregado: pendiente → aprobada | rechazada; caducada se calcula
│   │   └── TransferRequestRepository.php
│   └── Correction/
│       ├── CorrectionRequest.php       agregado: pendiente → atendida
│       └── CorrectionRequestRepository.php
├── Application/
│   ├── RegisterStudent/ · UpdateStudent/ · ChangeHealth/
│   ├── WithdrawStudent/ · ReinstateStudent/
│   ├── UpdateGuardian/ · InviteGuardian/ · LinkGuardianAccount/ (escucha MemberJoined)
│   ├── RequestTransfer/ · ApproveTransfer/ · RejectTransfer/
│   ├── RequestCorrection/ · ResolveCorrection/
│   ├── ListStudents/ · ShowStudent/ · FindGuardian/ · ListTransfers/ · ListCorrections/   queries (A-08)
│   ├── GuardianStudents/               queries del representante con cuenta (EST-R08.2, R08.3)
│   ├── StudentAudit.php                entradas de la bitácora
│   ├── SoleLegalGuardianBlocker.php    implementa IdentityAccess\Application\Account\DeletionBlocker (EST-R08.6)
│   └── Port/
│       ├── StudentPermissions.php      canManage() / canView() (S-04, EST-NF01)
│       └── TransferCopier.php          crea la ficha en el plantel destino (ADR 0008)
└── Infrastructure/
    ├── Persistence/                    modelos Eloquent, repositorios, query services, mappers
    ├── GateStudentPermissions.php
    ├── TenantSwitchingTransferCopier.php
    └── StudentsServiceProvider.php
```

Fuera de `src/`:

- **Controladores:** `app/Http/Controllers/StudentController.php`,
  `GuardianController.php`, `TransferController.php`,
  `GuardianPortalController.php` y `CorrectionController.php`.
- **FormRequests:** `StudentRequest`, `GuardianRequest`, `WithdrawalRequest`,
  `TransferRequest` (HTTP) y `CorrectionRequest` (HTTP).
- **Páginas:** `resources/js/Pages/Students/{Index,Show,Form}.vue`,
  `Transfers/Index.vue`, `Corrections/Index.vue`, `MyStudents/{Index,Show}.vue`,
  y las entradas «Estudiantes», «Traslados» y «Mis representados» del menú.
- **Migraciones:**
  - las tablas de [Datos](#datos);
  - la re-siembra del permiso `gestionar-estudiantes`.

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Student` | Agregado | `register(PersonalData, StudentIdentification, list<Guardianship>, HealthRecord, today)`. Nombres, apellidos, sexo, fecha de nacimiento, nacionalidad e identificación obligatorios; las violaciones se reportan juntas (R01.1, R01.2). Nacimiento no posterior a hoy (R01.3). Al menos un representante legal (R03.5) y exactamente un contacto principal (R03.6). Coherencia de la C.E. (abajo). `update(...)` con las mismas reglas (R01.5). `changeIdentification(NationalId)`: si tenía C.E., la guarda en `previousSchoolIds` (R02.7). `withdraw(date, reason, note, today)`: solo si está activo, fecha no futura (R06.1, R06.2). `reinstate()`: solo si está retirado; conserva la lista de retiros (R06.4). `changeHealth(HealthRecord)` (R04.1). |
| Coherencia de la C.E. | Regla en `Student` | Se comprueba al **asignar** una C.E. (alta, edición) y al **desvincular** un representante: `AA` = año de nacimiento (R02.4); `N`+`C` = la C.I. de algún representante vinculado (R02.5); no se puede desvincular al representante cuya C.I. forma la C.E. vigente (R03.7). Cada `Guardianship` lleva el documento de su representante, cargado por el repositorio. |
| `NationalId` | VO | `V` o `E` y 1–8 dígitos; acepta puntos y guiones al entrar y los quita (R02.2). `paddedNumber()` completa a 8 dígitos. |
| `SchoolId` | VO | Exactamente 12 caracteres: `N` ∈ {V, E}, `T` ∈ 1–4, `AA` y `C` dígitos (R02.3). Expone `nationality()`, `birthType()`, `birthYearSuffix()`, `embeddedNationalId()`. |
| `StudentIdentification` | VO | Una `NationalId` o una `SchoolId` (R02.1). Valor normalizado para buscar y comparar (`V12345678`, `V11915432100`). |
| `GuardianDocument` | VO | `NationalId` o `Passport` (R03.2). Solo una `NationalId` puede formar una C.E. |
| `Guardian` | Agregado | Nombres, apellidos, documento, teléfono obligatorios (R03.1); correo y dirección opcionales (R03.9). `correct(...)` (R03.8). `linkAccount(accountId)` y `pendingInvitation(invitationId)` (R08.1). |
| `HealthRecord` | VO | Tres textos opcionales de hasta 1 000 caracteres. `isEmpty()` alimenta el aviso de R04.3. |
| `TransferRequest` | Agregado | `request(identification, destinationName, now)` (R07.1). `approve(now)` y `reject(reason, now)`: solo pendiente y no caducada (R07.5 – R07.7). `isExpired(now)`: 15 días naturales desde la solicitud (R07.7). |
| `CorrectionRequest` | Agregado | `submit(guardianId, ?studentId, text)` (R08.4); `resolve()` (R08.5). |

**Sin eventos de dominio propios**, como en SPEC-006 y SPEC-007: cada caso
de uso arma la entrada de la bitácora con `StudentAudit`, dentro de su
transacción (ADR 0005).

## Casos de uso (Application)

Todos toman el plantel de `CurrentTenant` y solo ven sus filas por la RLS
(R11.1, R11.2). Los de escritura comprueban
`StudentPermissions::canManage()` y las consultas `canView()` (R09.1, R09.3,
R09.4, S-04, EST-NF01); registran en la bitácora dentro de su transacción.
El permiso nuevo llega por la migración de re-siembra (R09.2).

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `RegisterStudentHandler` | `RegisterStudentCommand`: datos personales, identificación, salud, representantes (existentes por id o nuevos con sus datos; parentesco, legal, principal) | `StudentId` | R01.1 – R01.4, R02.1 – R02.6, R03.1 – R03.6, R03.9, R04.1, R10.1 |
| `UpdateStudentHandler` | `UpdateStudentCommand` (lo mismo, con id) | — | R01.5, R02.1 – R02.7, R03.3 – R03.7, R10.1 |
| `ChangeHealthHandler` | id, `HealthRecord` | — | R04.1, R10.3 |
| `UpdateGuardianHandler` | id del representante y sus datos | — | R03.2, R03.8, R03.9, R10.1 |
| `WithdrawStudentHandler` | id, fecha, motivo, observación | — | R06.1 – R06.3, R10.2 |
| `ReinstateStudentHandler` | id | — | R06.4, R10.2 |
| `InviteGuardianHandler` | id del representante, id del gestor | — | R08.1 |
| `LinkGuardianAccountHandler` | evento `MemberJoined` | — | R08.1 |
| `RequestTransferHandler` | identificación | `TransferRequestId` | R07.1 – R07.3, R10.2, NF04 |
| `ApproveTransferHandler` | id de la solicitud | — | R07.5, R07.8, R10.2, R11.3 |
| `RejectTransferHandler` | id, motivo | — | R07.6, R10.2 |
| `RequestCorrectionHandler` | id de la cuenta, ¿estudiante?, texto | — | R08.4 |
| `ResolveCorrectionHandler` | id | — | R08.5 |
| `ListStudentsQuery` | texto, ¿incluir retirados?, página | `StudentPage` | R05.1 – R05.3, R02.8, NF03 |
| `ShowStudentQuery` | id, `includeHealth` | `StudentView` | R04.2, R04.3, R05.4 |
| `FindGuardianQuery` | documento | `?GuardianView` | R03.3 (el formulario lo usa para no duplicar) |
| `ListTransfersQuery` | — | entrantes (como origen) y salientes (como destino), con estado y caducidad | R07.4, R07.6, R07.7 |
| `GuardianStudentsQuery` | id de la cuenta, ¿estudiante? | lista / `StudentView` sin salud | R08.2, R08.3 |
| `ListCorrectionsQuery` | — | pendientes y atendidas | R08.4, R08.5 |
| `SoleLegalGuardianBlocker` | cuenta | motivos | R08.6 |

Detalles:

- **Identificación única (R02.6).** El caso de uso la comprueba con
  `StudentRepository::isIdentificationTaken(identification, except)`. El
  índice único es el respaldo; si salta, el repositorio lanza `Conflict`.
  El mensaje de R02.6 sugiere otro tipo de parto cuando la identificación
  es una C.E.
- **Representante existente (R03.3).** Si llega un representante «nuevo» con
  un documento que ya existe en el plantel, el caso de uso vincula el
  existente y **no** sobrescribe sus datos (se corrigen con
  `UpdateGuardian`).
- **Corregir la C.I. de un representante (R03.8).** No invalida las C.E. ya
  asignadas: la coherencia se comprueba al asignar una C.E. o al desvincular
  (ver [Decisiones](#decisiones-y-alternativas)).
- **Invitar al representante (R08.1).** `InviteGuardianHandler` llama a
  `IdentityAccess\Application\Members\InviteMemberHandler`. Lo hace con el
  email y el nombre del representante y el rol Representante, y guarda en
  el representante el id de la invitación. Si el email ya es de un miembro
  del plantel, vincula su cuenta directamente
  (`IdentityAccess\Application\Members\MemberDirectory::activeMemberByEmail`).
  `LinkGuardianAccountHandler` escucha `MemberJoined` (publicado por
  IdentityAccess al aceptar una invitación). Activa ese plantel y vincula
  la cuenta al representante con esa invitación pendiente.
- **Traslado (ADR 0008):**
  - `RequestTransferHandler` rechaza si el destino ya tiene esa
    identificación (R07.3) o ya tiene una solicitud pendiente igual;
    responde lo mismo exista o no el estudiante (R07.2);
  - `ApproveTransferHandler`, en una transacción:
    1. aprueba;
    2. retira al estudiante con el motivo «cambio de plantel»;
    3. llama a `TransferCopier::copyTo(destination, StudentTransferData)`,
       que crea al estudiante en el destino con sus representantes (sin
       vínculo de cuenta, R07.8), reutilizando los representantes que el
       destino ya tenga con el mismo documento;
  - si falta algún paso, no se guarda nada.
- **Acceso del representante (R08.2, R08.3).** Por el vínculo cuenta →
  representante, no por el rol. Un estudiante no vinculado responde 404 (el
  mismo `StudentNotFound`).

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `StudentRepository` | Domain | `EloquentStudentRepository` | Guarda la ficha, sus vínculos, retiros y C.E. anteriores juntos. `isIdentificationTaken`. |
| `GuardianRepository` | Domain | `EloquentGuardianRepository` | `byDocument`, `byPendingInvitation`, `linkedTo(accountId)`. |
| `TransferRequestRepository` | Domain | `EloquentTransferRequestRepository` | La RLS de ADR 0008 decide qué filas ve. |
| `CorrectionRequestRepository` | Domain | `EloquentCorrectionRequestRepository` | |
| `StudentPermissions` | Application | `GateStudentPermissions` | `gestionar-estudiantes` / `ver-estudiantes` por el *gate*, como `InstitutionPermissions` de SPEC-006. Los casos de uso no dependen de IdentityAccess. |
| `TransferCopier` | Application | `TenantSwitchingTransferCopier` | Único lugar que cambia de plantel dentro de la transacción (ADR 0008). Se prueba en aislamiento: un fallo en el destino deshace el retiro del origen. |
| `Clock` | Shared (existente) | `SystemClock` | Hoy, para el nacimiento, el retiro y la caducidad. |
| `GeographyDirectory` | Catalogs Application (existente) | `EloquentGeographyDirectory` | + `stateExists(id)` (R01.4). |
| `Countries` | Catalogs Application (nuevo) | lista estática ISO 3166-1 con nombres en español | País de nacimiento (R01.4). |
| `DeletionBlocker` | IdentityAccess Application (existente) | `SoleLegalGuardianBlocker` (Students) | Recorre los planteles de la cuenta con `InstitutionScope` y cuenta los estudiantes activos cuyo único representante legal es el suyo. |

## Adaptadores de entrada (`app/`)

En el dominio del plantel, con `auth`, `active` y `verified`.

| Ruta | Middleware | Controlador | Caso de uso |
|------|------------|-------------|-------------|
| `GET /estudiantes` | `permission:ver-estudiantes` | `StudentController@index` | `ListStudentsQuery` |
| `GET /estudiantes/{student}` | `permission:ver-estudiantes` | `@show` | `ShowStudentQuery` (salud si `gestionar-estudiantes`) |
| `GET /estudiantes/nuevo` · `/estudiantes/{student}/editar` | `permission:gestionar-estudiantes` | `@create` / `@edit` | — / `ShowStudentQuery` |
| `POST /estudiantes` · `PUT /estudiantes/{student}` | `permission:gestionar-estudiantes`, `throttle:30,1` | `@store` / `@update` (`StudentRequest`) | `RegisterStudent` / `UpdateStudent` |
| `PUT /estudiantes/{student}/salud` | ídem | `@health` | `ChangeHealth` |
| `POST /estudiantes/{student}/retiro` · `/reincorporacion` | ídem | `@withdraw` (`WithdrawalRequest`) / `@reinstate` | `WithdrawStudent` / `ReinstateStudent` |
| `GET /representantes/buscar?documento=` | `permission:gestionar-estudiantes`, `throttle:60,1` | `GuardianController@find` | `FindGuardianQuery` |
| `PUT /representantes/{guardian}` · `POST /representantes/{guardian}/invitacion` | `permission:gestionar-estudiantes`, `throttle:30,1` | `@update` / `@invite` | `UpdateGuardian` / `InviteGuardian` |
| `GET /traslados` | `permission:gestionar-estudiantes` | `TransferController@index` | `ListTransfersQuery` |
| `POST /traslados` | `permission:gestionar-estudiantes`, limitador `transfer-requests` (20 por hora por plantel) | `@store` | `RequestTransfer` |
| `POST /traslados/{transfer}/aprobar` · `/rechazar` | `permission:gestionar-estudiantes`, `throttle:30,1` | `@approve` / `@reject` | `ApproveTransfer` / `RejectTransfer` |
| `GET /correcciones` · `POST /correcciones/{correction}/atendida` | `permission:gestionar-estudiantes` | `CorrectionController` | `ListCorrections` / `ResolveCorrection` |
| `GET /mis-representados` · `/mis-representados/{student}` | (cuenta vinculada) | `GuardianPortalController` | `GuardianStudentsQuery` |
| `POST /mis-representados/correcciones` | `throttle:10,1` | `@correction` | `RequestCorrection` |

- **Validación en el borde (S-09):** las FormRequests comprueban la forma
  (longitudes, fechas, enums, listas). Las reglas de la C.E., los
  representantes legales y la unicidad son del dominio y del caso de uso.
- **Permisos en la interfaz:** `HandleInertiaRequests` comparte
  `can.viewStudents`, `can.manageStudents` y `isGuardian` (cuenta vinculada).
- **Sin datos de salud en Inertia** para quien no gestiona: el controlador
  ni siquiera los recibe del query (S-10, R04.2).

## Datos

Todas las tablas con `belongsToInstitution()` y `RowLevelSecurity::enable()`
(S-01), salvo `transfer_requests`, con sus propias políticas (ADR 0008).

```
students
  id, institution_id, given_names(120), family_names(120), sex (CHECK female|male),
  birth_date, nationality (CHECK V|E),
  identification_type (CHECK national_id|school_id), identification varchar(12),
  birth_country char(2) NULL, birth_state_id NULL → states, parish_id NULL → parishes,
  address varchar(500) NULL, emergency_phone varchar(30) NULL,
  allergies text NULL, medical_conditions text NULL, disability text NULL,
  status (CHECK active|withdrawn), timestamps
  UNIQUE (institution_id, identification)
  INDEX (institution_id, status, family_names)

student_previous_identifications
  id, institution_id, student_id → students (cascade), identification varchar(12), replaced_on
  INDEX (institution_id, identification)

guardians
  id, institution_id, given_names(120), family_names(120),
  document_type (CHECK national_id|passport), document varchar(20),
  phone(30), email(255) NULL, address(500) NULL,
  account_id NULL → users, pending_invitation_id NULL → invitations, timestamps
  UNIQUE (institution_id, document_type, document)
  UNIQUE (institution_id, account_id) WHERE account_id IS NOT NULL

guardianships
  id, institution_id, student_id → students (cascade), guardian_id → guardians,
  relationship (CHECK mother|father|other), is_legal bool, is_primary bool
  UNIQUE (student_id, guardian_id)
  UNIQUE (student_id) WHERE is_primary          -- un solo contacto principal (R03.6)

student_withdrawals
  id, institution_id, student_id → students (cascade), withdrawn_on, reason
  (CHECK moved|changed_school|economic_or_family|other), note varchar(500) NULL, reinstated_on NULL

transfer_requests                               -- políticas propias, ADR 0008
  id, institution_id (destino), destination_name, identification varchar(12),
  status (CHECK pending|approved|rejected), origin_institution_id NULL → institutions,
  rejection_reason varchar(500) NULL, requested_at, answered_at NULL
  UNIQUE (institution_id, identification) WHERE status = 'pending'
  INDEX (identification) WHERE status = 'pending'

correction_requests
  id, institution_id, guardian_id → guardians, student_id NULL → students,
  text varchar(1000), status (CHECK pending|resolved), resolved_at NULL, timestamps
```

- **«Al menos un representante legal» no tiene restricción en la base de
  datos:** la garantiza el agregado, que se guarda entero en una
  transacción.
- **Búsqueda (NF03):** `ILIKE` sobre nombres, apellidos, identificación y
  C.E. anteriores, con el patrón escapado (S-09). Con 5 000 filas por
  plantel, un recorrido filtrado por `institution_id` cabe en el umbral. No
  hace falta `pg_trgm`, y así no hay otra extensión que comprobar en
  producción. Lo vigila un test del grupo `perf`.
- **Permiso:** una migración de re-siembra añade `gestionar-estudiantes` a
  Administrador y Director en los planteles existentes (R09.2), como en
  SPEC-006.

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Agregado (`Student`) | La coherencia de la C.E. depende de los representantes vinculados, y «al menos un legal, un principal» depende de todos los vínculos: deben cambiar juntos y validarse en un solo lugar. |
| Agregado aparte (`Guardian`) | Un representante se comparte entre hermanos (R03.3, R03.8): si viviera dentro de `Student`, corregirlo exigiría tocar varias fichas. |
| Value Object (`NationalId`, `SchoolId`, `StudentIdentification`) | El formato y la normalización de las cédulas aparecen en el alta, la edición, la búsqueda, el traslado y la C.E.: un solo tipo los garantiza. |
| Puerto + adaptador (`TransferCopier`) | Cambiar de plantel dentro de una transacción es delicado (ADR 0008): queda confinado en un adaptador y se prueba solo. |
| Puerto + adaptador (`StudentPermissions`) | La comprobación fina de S-04 sin importar IdentityAccess. |
| Query service (`ListStudents`, `ShowStudent`, `GuardianStudents`) | Lecturas con y sin salud, paginadas y con búsqueda, sin hidratar agregados (A-08). |
| Evento entre módulos (`MemberJoined`) | Students necesita saber que una invitación se aceptó, sin que IdentityAccess conozca a Students. |
| Punto de extensión (`DeletionBlocker`) | Ya previsto por SPEC-004 para IDG-R08.3. |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Un Docente o Coordinador registra, edita o retira estudiantes | `permission:gestionar-estudiantes` en la ruta y `canManage()` en el caso de uso | Tests feature de 403 y unit de los handlers (R09.1, R09.4) |
| **S**poofing | Alguien se hace pasar por representante para ver a un menor | El acceso es por el vínculo cuenta → representante, creado solo por una invitación aceptada (o por el gestor si ya es miembro) | Tests de R08.1 – R08.3 |
| **T**ampering | Editar estudiantes de otro plantel enviando su id | RLS en todas las tablas; el plantel sale del subdominio | Tests de aislamiento (grupo `rls`) y feature (R11.1, R11.2) |
| **T**ampering | Aprobar un traslado ajeno, o uno caducado | La RLS de `transfer_requests` solo deja ver y actualizar al plantel del estudiante activo; el agregado rechaza lo caducado | Tests de la política (`rls`) y unit de `TransferRequest` |
| **T**ampering | Presentar a otro plantel como origen de una solicitud (SPEC-015 RLT-R05) | Al crear, el origen va vacío (política `INSERT`); un *trigger* sin privilegios solo deja que el plantel que responde se ponga a sí mismo como origen, una vez | `TransferRequestOriginTest` (`rls`) |
| **R**epudiation | «Yo no retiré a ese estudiante», «no aprobé el traslado» | Bitácora de cada cambio, en las dos puntas del traslado | Tests de R10.1, R10.2 |
| **I**nformation disclosure | Averiguar en qué plantel está un estudiante con solicitudes de traslado | Respuesta idéntica exista o no (R07.2); el destino no ve el origen hasta la respuesta; límite de 20 solicitudes por hora por plantel | Test feature de respuesta idéntica y del límite (NF04) |
| **I**nformation disclosure | Ver datos de salud de un menor sin necesitarlos | El query no los incluye sin `gestionar-estudiantes`; no van a la bitácora ni a los logs | Tests de R04.2, R04.3, R10.3 y de NF02 |
| **I**nformation disclosure | Un representante ve a niños que no son sus representados | `GuardianStudentsQuery` filtra por el vínculo; un id ajeno da 404 | Test de R08.3 |
| **I**nformation disclosure | Un error de código escribe datos del origen en el destino durante el traslado | Cambio de plantel solo en `TransferCopier`; test de arquitectura | Test de arquitectura y de integración del copiador |
| **D**enial of service | Solicitudes de traslado o búsquedas masivas | Limitadores por ruta (`transfer-requests`, `throttle`) y paginación | Reglas en las rutas; test del límite |
| **E**levation of privilege | Un gestor de estudiantes se da acceso de Administrador al invitar | `InviteGuardian` siempre pide el rol Representante; la política de asignación de roles de IdentityAccess sigue vigente | Test de R08.1 (rol fijo) |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario (ejemplos) |
|----------------------|------|-------------------------------|
| `BusinessRuleViolation(s)` en el campo | 422 | «La cédula escolar debe tener 12 caracteres.» · «El año de la cédula escolar no coincide con la fecha de nacimiento.» · «La cédula escolar debe formarse con la cédula de uno de sus representantes.» · «Ya hay un estudiante con esa identificación en el plantel. Si es un hermano nacido el mismo año, usa otro tipo de parto.» · «El estudiante necesita al menos un representante legal.» · «No puedes quitar al representante cuya cédula forma la cédula escolar.» · «Indica la fecha y el motivo del retiro.» |
| `StudentNotFound`, `TransferNotFound`, `GuardianNotFound` (`NotFound`) | 404 | «No encontramos ese estudiante.» |
| `TransferExpired`, `StudentNotActive` (`BusinessRuleViolation`) | 422 | «La solicitud caducó.» · «El estudiante ya está retirado.» |
| `Conflict` (índice único) | 409 | «Otro gestor guardó un cambio a la vez; vuelve a intentarlo.» · al aprobar: «El plantel destino ya registró a ese estudiante; rechaza la solicitud.» |
| `AuthorizationDenied` | 403 | «No tienes permiso para gestionar estudiantes.» |

## Estrategia de pruebas

- **Dominio (unit):**
  - `NationalId`, `SchoolId` (los ejemplos de los requisitos y cada carácter
    inválido), `StudentIdentification`;
  - `Student`: obligatorios, nacimiento, legal y principal, coherencia de
    la C.E., paso a C.I., retiro y reincorporación;
  - `Guardian`, `TransferRequest` (caducidad en el día 15 y el 16) y
    `CorrectionRequest`.
- **Aplicación (unit, en memoria):**
  - cada handler, con permisos denegados, y la bitácora dentro de la
    transacción y sin valores de salud;
  - el representante existente que no se sobrescribe;
  - el traslado con un copiador en memoria que falla, para probar que no se
    retira a nadie;
  - el vínculo de la cuenta al recibir `MemberJoined`;
  - el bloqueador de eliminación.
- **Infraestructura (integración, Postgres):**
  - repositorios;
  - `TenantTableTest` y aislamiento de cada tabla (grupo `rls`);
  - las políticas de `transfer_requests`: el destino no ve el origen, un
    tercer plantel no ve nada, solo el plantel del estudiante activo ve la
    pendiente;
  - `TenantSwitchingTransferCopier`: la ficha queda en el destino y el
    retiro en el origen, o nada;
  - índices únicos y `CHECK`;
  - búsqueda en menos de 300 ms con 5 000 estudiantes (grupo `perf`).
- **Feature (HTTP):**
  - pantallas por rol y 403;
  - alta con un representante nuevo y uno existente;
  - salud visible solo para gestores;
  - retiro y reincorporación;
  - traslado de punta a punta entre dos planteles, incluida la respuesta
    idéntica y el límite de frecuencia;
  - invitación y vista del representante, con el 404 de un estudiante
    ajeno;
  - correcciones;
  - bloqueo de la eliminación de cuenta.
- **Arquitectura:**
  - controladores en `MIGRATED_CONTROLLERS`;
  - `Students` usa otros módulos solo por su `Application`;
  - `TenantActivator` solo en `TenantSwitchingTransferCopier` y en
    `LinkGuardianAccountHandler`.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Traslado con RLS propia en `transfer_requests` y copia atómica cambiando de plantel (ADR 0008) | Índice de plataforma, lectura central de `students`, instantánea importada después | Ver ADR 0008: las alternativas exponen datos de menores fuera de su plantel o dejan estados intermedios. |
| `Guardian` como agregado propio del plantel | Representante dentro de la ficha de cada estudiante | Hermanos comparten representante (R03.3, R03.8). |
| La coherencia de la C.E. se comprueba al asignarla y al desvincular, no al corregir la C.I. del representante | Bloquear la corrección de una C.I. embebida en una C.E. | Si la C.I. del representante tenía un error, corregirla primero es lo natural, y la C.E. se corrige después. Bloquearlo dejaría un bloqueo mutuo (la C.E. necesita la C.I. correcta y viceversa). |
| Acceso del representante por el vínculo de su cuenta, no por el rol | Por el rol Representante | Un Docente que además es padre ve a sus hijos sin cambiarle los roles, y un Representante sin vínculo no ve a nadie. |
| Si el email ya es de un miembro del plantel, se vincula directamente | Invitarlo siempre | IdentityAccess no deja invitar a quien ya es miembro (`claimEmail`). |
| `MemberJoined`, primer evento entre módulos por el `EventBus` | Que Students busque al aceptar por email | Vincular por coincidencia de email podría dar acceso a un menor a la persona equivocada. |
| Países como lista estática ISO 3166-1 en `Catalogs\Application\Countries` | Texto libre, o una dependencia nueva (Symfony Intl) | Se valida y se muestra en español sin cambiar dependencias. |
| Caducidad calculada al leer | Tarea programada que marca las caducadas | Sin infraestructura de colas ni de cron; el estado se deriva de la fecha. |
| Búsqueda con `ILIKE` | `pg_trgm` | Suficiente para 5 000 estudiantes, sin otra extensión en producción. |

## Cambios en otras specs y módulos

- **SPEC-004 / IdentityAccess:**
  - `InviteMemberHandler` devuelve el id de la invitación (hoy no devuelve
    nada);
  - `MemberDirectory` gana `activeMemberByEmail`;
  - aceptar una invitación (`AcceptInvitationHandler`,
    `JoinInstitutionHandler`) publica `MemberJoined(institutionId,
    accountId, invitationId)` por el `EventBus`;
  - IDG-R08.3 queda cubierto por `SoleLegalGuardianBlocker` (Students).
- **SPEC-002:** IAM-R11.1 suma `gestionar-estudiantes` para Administrador y
  Director.
- **SPEC-003 / Catalogs:** `GeographyDirectory::stateExists` y la lista
  `Countries`.
- **Constitución:** `Students` se añade a la lista de módulos.

## Riesgos y plan de rollback

- **ADR 0008 (traslados).** Es el cambio de mayor riesgo:
  - las políticas tienen tests de aislamiento propios;
  - el cambio de plantel queda confinado y con test de arquitectura;
  - si algo falla en producción, se desactivan las rutas de traslado; el
    resto del módulo no depende de ellas.
- **Datos de menores.** Antes del merge, `/security-review`, sobre todo:
  - salud;
  - traslados;
  - acceso del representante.

  NF02 se verifica con un test que busca datos de salud en los registros
  de la bitácora.
- **Cambios en IdentityAccess** (evento y valor de retorno): son aditivos,
  y sus tests actuales deben seguir pasando sin cambios (Q-03).
- **Conservación indefinida** de fichas de retirados, hasta la validación
  legal (D1).
- **Migraciones:**
  - todas son tablas nuevas, sin migración de datos;
  - el `down()` las borra en orden inverso y quita el permiso;
  - se prueba `migrate:fresh --seed` dos veces y el rollback.

## Notas de implementación

Ajustes hechos al implementar el PR A, sin cambiar ningún criterio:

- **`StudentForm` (Application).** El alta y la edición comparten la
  revisión del formulario:
  - junta en un solo `BusinessRuleViolations` los datos personales, la
    identificación, los catálogos (país, estado, parroquia), la unicidad y
    cada representante, con el campo `guardians.{n}.{dato}`;
  - guarda los representantes nuevos dentro de la transacción del caso de
    uso.

  Las reglas que dependen de todos los vínculos (legal, principal,
  coherencia de la C.E.) siguen en el agregado.
- **Validación en el borde solo de forma.** `StudentRequest`,
  `GuardianRequest` y `WithdrawalRequest` comprueban tipos y longitudes;
  qué datos son obligatorios lo dice el dominio, para que cada error tenga
  un solo origen. Los datos de salud se validan en el controlador
  (`health`), sin FormRequest propio.
- **Orden de la lista en español.** `ORDER BY ... COLLATE "es-x-icu"`
  (Á con A, Ñ después de N), sin depender de la intercalación por defecto
  de la base. **Producción necesita PostgreSQL con ICU**, como el de
  desarrollo.
- **Lista de países.** Se generó una vez con los 249 códigos ISO 3166-1 y
  los nombres en español de CLDR (`Intl.DisplayNames`). Queda como
  constante en `Catalogs\Application\Countries`, sin depender de `intl` en
  tiempo de ejecución.
- **Fecha de una C.E. anterior.** `student_previous_identifications.replaced_on`
  es el día en que se guardó el cambio a C.I.
- **Test de permisos de los roles base.** Se renombró a «each base role
  carries the permissions its specs give it», para no renombrarlo con cada
  spec. Se actualizaron sus referencias en las matrices de SPEC-006 y
  SPEC-007.
- **Lista de módulos de la constitución.** No se tocó: ya estaba
  desactualizada (falta `Audit` y `AcademicCalendar`), y cambiarla requiere
  un ADR. Los tests de capas descubren los módulos solos.

Ajustes del PR B (traslados, ADR 0008), sin cambiar ningún criterio:

- **El estado «caducada» también se guarda.** Se calcula al leer, como
  dice el diseño. Pero el índice «una solicitud pendiente por estudiante y
  destino» bloquearía pedir de nuevo tras la caducidad. Por eso, al volver a
  pedir, `RequestTransfer` marca `expired` la vieja
  (`TransferRequest::expire`), y el `CHECK` admite ese valor.
- **La solicitud conoce su destino y el nombre del origen.**
  `TransferRequest` lleva:
  - `destinationId`, que es la columna `institution_id`: el copiador sabe
    dónde escribir, y el destino no puede aprobar su propia solicitud;
  - `origin_name`, que se guarda al responder: el destino ve quién respondió
    sin leer la tabla de planteles de Tenancy.
- **`Tenancy\Application\InstitutionContexts`.** Es una consulta pública
  nueva que da el `TenantContext` de un plantel por su id. El copiador la
  usa para activar el destino.
- **Savepoint en el copiador.** Las escrituras en el destino van en un
  `DB::transaction` anidado. Si fallan (p. ej. el destino ya registró al
  estudiante), se deshacen hasta el savepoint, la transacción del origen
  sigue usable para reactivarlo, y luego se deshace entera.
- **RLS genérica.** El helper se llama
  `RowLevelSecurity::enableAddressedByIdentification(tabla, titulares)`.
  `TenantTableTest` tiene una categoría nueva (`ADDRESSED_TABLES`) que exige
  exactamente sus tres políticas.
- **Retiro por traslado.** Se registra con la fecha de la aprobación y la
  observación «Traslado a {plantel destino}».
- **Bitácora:**
  - en el destino: `students.transfer.requested` y
    `students.student.transferred_in`;
  - en el origen: `students.student.withdrawn` y
    `students.transfer.approved` o `.rejected`.

Ajustes del PR C (cuenta del representante), sin cambiar ningún criterio:

- **Contratos de IdentityAccess con `int` (P-04, aprobado el 2026-10-22).**
  `DeletionBlocker::reasonsFor()` e `InstitutionScope::forEachInstitutionOf()`
  recibían un `AccountId`, del dominio de IdentityAccess. Students no
  puede importarlo (A-06). Por eso ahora los dos reciben el id de la cuenta
  como `int`, lo propio de un punto de extensión entre módulos.
  - El agregador interno `DeletionBlockers` sigue con `AccountId`.
  - El bloqueador de prueba de `AccountDeletionTest` solo cambia el tipo
    del parámetro.
- **Puerto `GuardianInvitations`.** `InviteGuardian` no llama directamente
  a `InviteMemberHandler` y `MemberDirectory`, sino a través de este puerto.
  Así el caso de uso se prueba en aislamiento. Su adaptador
  (`IdentityAccessGuardianInvitations`) usa esos dos casos de uso
  públicos.
- **Acceso del representante.** Va por el vínculo cuenta → representante
  (`LinkedGuardian`). Una cuenta sin vínculo en el plantel recibe 403 en
  «Mis representados»; un estudiante que no representa, 404 (EST-R08.3). La
  prop compartida `auth.isGuardian` muestra la entrada del menú.
- **Lo que ve el representante.** La ficha básica sin salud; de los otros
  representantes, solo el nombre y el parentesco, sin documentos ni
  teléfonos.
- **Correcciones sin bitácora.** Son mensajes, no cambios de datos; el
  cambio real se hace en la ficha y queda en la bitácora por su caso de
  uso.
