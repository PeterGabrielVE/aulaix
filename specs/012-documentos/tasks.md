---
spec: 012-documentos
estado: implementando
fecha: 2026-11-27
aprobada: 2026-11-27
diseño: design.md
adr: ../adr/0015-documentos-pdf-inmutables-y-verificacion-qr.md
---

# Tareas — Documentos del plantel y verificación QR

Reglas:

- Una tarea = un cambio pequeño, revisable y que deja la suite en verde.
- Cada tarea cita los criterios que cubre y el test que la verifica.
- Orden de dentro hacia fuera: dominio → aplicación → infraestructura →
  adaptadores de entrada.
- TDD (Q-02): primero el test en rojo.
- Marca `[x]` al terminar y añade el test a la matriz de `requirements.md`.

---

## PR A — Fundaciones, dominio, datos, permisos y motor PDF/QR

- [x] **T-01** — Instalar `dompdf/dompdf` (`^3.1`), verificar `composer audit
  --locked` y actualizar la regla `no module depends on Evaluation` en
  `tests/Architecture/LayersTest.php` para que proteja a los módulos base sin
  bloquear a `Documents` (`A-06`).
  - Criterios: DOC-NF04
  - Test: `tests/Architecture/LayersTest.php` › «the modules Evaluation builds on never depend on it»
  - Hecho cuando: `dompdf/dompdf` está en `composer.json`/`composer.lock`,
    `composer audit --locked` pasa sin vulnerabilidades y `LayersTest` está en
    verde.

- [x] **T-02** — Permiso `emitir-documentos`: añadir `Permission::IssueDocuments`
  a `src/IdentityAccess/Domain/Role/Permission.php`, asignarlo por defecto a
  `Administrador`, `Director` y `Coordinador` en `BaseRole.php`, y crear la
  migración de datos idempotente `grant_issue_documents_permission` (con `up()`
  y `down()` verificados).
  - Criterios: DOC-R08.1
  - Test: `tests/Feature/Documents/Infrastructure/GrantIssueDocumentsPermissionMigrationTest.php` › «grants emitir-documentos to Administrador, Director and Coordinador in existing institutions and rolls back cleanly»
  - Hecho cuando: planteles nuevos (vía `RolePermissionSeeder`) y existentes
    (vía migración) conceden `emitir-documentos` a los tres roles y el permiso
    se puede asignar persona a persona.

- [x] **T-03** — Capa de dominio de `src/Documents/Domain/`:
  `DocumentId`, `DocumentKind`, `DocumentStatus`, `VerificationCode` (128 bits
  de entropía, normalización, SHA-256, `codeHint`), `StudentPublicSummary`
  (iniciales + últimos 4 dígitos), `ValidityPeriod` (1–365 días),
  `GoodConductNote` (0–300 caracteres), `VoidReason` (5–500 caracteres),
  agregado `IssuedDocument` (`issue`, `reconstitute`, `void`, `statusAt`,
  `isDownloadable`), `IssuedDocumentRepository` y `DocumentScopePolicy`.
  - Criterios: DOC-R04.6, DOC-R05.4, DOC-R06.1, DOC-R06.2, DOC-R06.3,
    DOC-R07.1, DOC-R07.2, DOC-R07.3, DOC-R08.3, DOC-NF02
  - Tests:
    - `tests/Unit/Documents/Domain/VerificationCodeTest.php` › «generates non-sequential 128-bit opaque codes and normalizes input for hashing»
    - `tests/Unit/Documents/Domain/StudentPublicSummaryTest.php` › «derives student initials and last four digits without exposing full name or ID»
    - `tests/Unit/Documents/Domain/IssuedDocumentTest.php` › «tracks validity, expiration, immutability and irreversible voiding»
    - `tests/Unit/Documents/Domain/DocumentScopePolicyTest.php` › «restricts Coordinator to coordinated grades while allowing full-scope issuers»
  - Hecho cuando: todas las invariantes de dominio tienen tests unitarios sin
    Laravel en verde y `LayersTest` confirma que `Documents\Domain` es PHP puro.

- [x] **T-04** — Persistencia de documentos emitidos: migración
  `create_issued_documents_table` (`belongsToInstitution()`,
  `RowLevelSecurity::enable()`, constraints `CHECK` e índices),
  `IssuedDocumentModel`, `IssuedDocumentMapper`,
  `EloquentIssuedDocumentRepository` y `EloquentDocumentHistoryDirectory`.
  - Criterios: DOC-R05.4, DOC-R05.5, DOC-R10.1, DOC-R10.3
  - Tests:
    - `tests/Feature/Documents/Infrastructure/IssuedDocumentRepositoryTest.php` › «persists, finds by verification hash, enforces constraints and isolates by RLS»
    - `tests/Feature/Tenancy/TenantTableTest.php` (incluye `issued_documents` automáticamente)
  - Hecho cuando: la tabla tiene RLS activa, los `CHECK` rechazan estados
    inconsistentes, `migrate:reset` + `migrate` funcionan limpio y `TenantTableTest`
    pasa.

- [x] **T-05** — Almacenamiento privado y motor PDF/QR: puertos
  `DocumentStorage`, `PdfRenderer`, `QrCodeGenerator`; adaptadores
  `LaravelDocumentStorage` (`institutions/{id}/documents/{random40}.pdf`,
  `isOwn()` estricto, contabilizado por `UsedStorageQuery`),
  `BaconQrCodeGenerator` y `DompdfRenderer` (`isRemoteEnabled=false`,
  `isPhpEnabled=false`, `isJavascriptEnabled=false`, plantillas Blade con
  escape estricto `{{ }}` para boletas por nivel, lote consolidado y las 5
  constancias).
  - Criterios: DOC-R05.1, DOC-R05.2, DOC-R05.3, DOC-R05.4, DOC-R07.1,
    DOC-R10.1, DOC-NF04
  - Tests:
    - `tests/Feature/Documents/Infrastructure/LaravelDocumentStorageTest.php` › «stores PDFs under the active institution folder and refuses reading paths of another institution»
    - `tests/Feature/Documents/Infrastructure/DompdfRendererTest.php` › «renders valid PDFs with inline logo, signatures and QR while escaping HTML and blocking remote resources»
  - Hecho cuando: los PDFs generados son válidos (`%PDF-`), contienen el texto
    y QR esperados, escapan inyección HTML sin abrir conexiones remotas y
    `UsedStorageQuery` refleja su peso.

- [x] **T-06** — Consultas públicas entre módulos (`A-06`) y proveedor de
  `Documents`:
  - en `Tenancy`: `InstitutionProfileQuery` (datos del encabezado + nombres y
    cargos de firmas + aviso de faltantes), `ResolveTenantForVerificationHandler`
    (resuelve activos e inactivos) y `TenantActivator::forReadOnlyRequest()`
    (`SET TRANSACTION READ ONLY` en PostgreSQL);
  - en `Evaluation`: `StudentReportCardQuery` y `SectionReportCardsQuery`
    (notas por lapso, definitivas, revisión, definitiva tras revisión,
    pendientes, literales e informes descriptivos);
  - en `Documents/Infrastructure/`: `TenancyInstitutionFacts`,
    `SchoolAcademicFacts`, `SchoolStudentRecordFacts`,
    `SchoolEvaluationFacts`, `GateDocumentPermissions` y
    `DocumentsServiceProvider`.
  - Criterios: DOC-R01.1, DOC-R01.2, DOC-R01.3, DOC-R02.1, DOC-R02.2,
    DOC-R02.3, DOC-R05.1, DOC-R05.2, DOC-R05.3, DOC-R07.5, DOC-R08.2,
    DOC-R08.3, DOC-NF01
  - Tests:
    - `tests/Feature/Documents/Infrastructure/EvaluationReportCardQueryTest.php` › «builds term and final report card data for Inicial, Primaria and Media General including revisions and pendings»
    - `tests/Feature/Tenancy/ReadOnlyVerificationTransactionTest.php` › «resolves active and inactive institutions in a PostgreSQL read-only transaction»
  - Hecho cuando: `Documents` lee todos los datos exclusivamente vía capas
    `Application` ajenas y `LayersTest` está en verde.

---

## PR B — Casos de uso de emisión, historial, descarga, anulación y verificación QR

- [ ] **T-07** — Guardia `DocumentAccess`, payloads `DocumentAudit` (sin
  binarios ni código completo), `DocumentNotFound`, `IssueTermReportHandler` e
  `IssueFinalReportHandler` (Inicial, Primaria, Media General, solo lapsos
  cerrados, retirados tras cierre del lapso, rollback de PDF ante fallo).
  - Criterios: DOC-R01.1, DOC-R01.2, DOC-R01.3, DOC-R01.4, DOC-R01.5,
    DOC-R01.6, DOC-R02.1, DOC-R02.2, DOC-R02.3, DOC-R02.4, DOC-R05.1,
    DOC-R05.2, DOC-R05.3, DOC-R05.4, DOC-R07.1, DOC-R08.2, DOC-R08.3,
    DOC-R09.1, DOC-R09.2, DOC-R10.3, DOC-NF01, DOC-NF02
  - Test: `tests/Unit/Documents/Application/IssueReportCardTest.php` › «issues term and final reports only for closed terms, handles withdrawn students, enforces scope and audits without secrets»
  - Hecho cuando: todos los casos unitarios de boleta por lapso y boleta final
    pasan en memoria y limpian archivos si la transacción falla.

- [ ] **T-08** — `IssueSectionBatchHandler`: emisión en lote de boletas por
  sección (lapso cerrado o boleta final), orden alfabético por apellidos y
  nombres, registro individual con código QR único por estudiante, evento único
  en bitácora y rollback atómico de filas y PDFs ante error.
  - Criterios: DOC-R03.1, DOC-R03.2, DOC-R03.3, DOC-R03.4, DOC-R05.1,
    DOC-R05.2, DOC-R05.3, DOC-R05.4, DOC-R07.1, DOC-R08.2, DOC-R08.3,
    DOC-R09.1, DOC-R09.2, DOC-NF01, DOC-NF02
  - Test: `tests/Unit/Documents/Application/IssueSectionBatchTest.php` › «issues a consolidated PDF ordered alphabetically with one immutable record and unique QR per eligible student»
  - Hecho cuando: secciones vacías o con lapso abierto se rechazan sin crear
    registros, y cada estudiante del lote queda registrado y verificable.

- [ ] **T-09** — `IssueCertificateHandler`: las 5 constancias del plantel
  (estudio, inscripción, retiro, buena conducta con observación opcional
  0–300 caracteres, prosecución/egreso bloqueando «pendiente de egresar»),
  vigencia temporal 1–365 días (90 por defecto) vs sin vencimiento, y auditoría.
  - Criterios: DOC-R04.1, DOC-R04.2, DOC-R04.3, DOC-R04.4, DOC-R04.5,
    DOC-R04.6, DOC-R05.1, DOC-R05.2, DOC-R05.3, DOC-R05.4, DOC-R07.1,
    DOC-R08.2, DOC-R08.3, DOC-R09.1, DOC-R09.2, DOC-NF01, DOC-NF02
  - Test: `tests/Unit/Documents/Application/IssueCertificateTest.php` › «issues each certificate kind under its eligibility and expiration rules and rejects invalid requests»
  - Hecho cuando: cada una de las 5 constancias valida sus precondiciones,
    vigencia, observación opcional y alcance de Coordinador.

- [ ] **T-10** — `DownloadDocumentQuery`, `VoidDocumentHandler`,
  `ListDocumentsQuery` y `ListStudentDocumentsQuery`: re-descarga inmutable
  bloqueada para documentos anulados, anulación con motivo obligatorio 5–500,
  filtros del historial, aviso de datos faltantes del plantel y alcance del
  Coordinador.
  - Criterios: DOC-R05.3, DOC-R05.4, DOC-R05.5, DOC-R06.1, DOC-R06.2,
    DOC-R06.3, DOC-R08.2, DOC-R08.3, DOC-R09.1, DOC-R09.2, DOC-R10.3, DOC-NF01
  - Test: `tests/Unit/Documents/Application/ManageIssuedDocumentsTest.php` › «lists, re-downloads valid documents, voids with reason, blocks re-download when voided and scopes by Coordinator grades»
  - Hecho cuando: los cuatro casos de uso pasan en memoria respetando S-04 y
    auditoría.

- [ ] **T-11** — `VerifyDocumentQuery`: verificación pública por hash del
  código, cálculo de estado (`válido`, `vencido`, `anulado`), exposición
  exclusiva de campos públicos mínimos (sin nombre completo, cédula completa,
  notas, observación ni motivo interno) y respuesta nula unificada cuando no
  existe.
  - Criterios: DOC-R07.2, DOC-R07.3, DOC-R07.4, DOC-R07.5
  - Test: `tests/Unit/Documents/Application/VerifyDocumentTest.php` › «returns minimal public verification data for valid, expired and voided documents and null for unknown codes»
  - Hecho cuando: ningún dato prohibido por `DOC-R07.3` existe siquiera en el
    DTO `PublicVerificationView`.

---

## PR C — HTTP, verificación QR, UI Inertia, RLS/PgBouncer, rendimiento y cierre

- [ ] **T-12** — Verificación pública QR sobre HTTP:
  middleware `ResolveTenantForVerification` (confinado en `LayersTest.php` a
  `ResolveTenantForVerificationHandler`), rate limiter `document-verification`
  (30 req/min por plantel e IP en `AppServiceProvider`),
  `DocumentVerificationController` (`GET /verificar/{code}`) y página pública
  `resources/js/Pages/Documents/Verify.vue` (móvil 360 px, WCAG 2.1 AA).
  - Criterios: DOC-R07.2, DOC-R07.3, DOC-R07.4, DOC-R07.5, DOC-R10.2,
    DOC-NF03, DOC-NF06
  - Test: `tests/Feature/Documents/DocumentVerificationTest.php` › «verifies valid, expired and voided codes without PII, throttles brute force and stays read-only on inactive institutions»
  - Hecho cuando: la ruta pública funciona sin login, aplica límite 429, sigue
    funcionando en solo lectura en un plantel inactivo y responde igual para
    códigos inexistentes o de otro plantel.

- [ ] **T-13** — Endpoints autenticados de documentos y navegación:
  `DocumentController`, FormRequests (`IssueTermReportRequest`,
  `IssueFinalReportRequest`, `IssueSectionBatchRequest`,
  `IssueCertificateRequest`, `VoidDocumentRequest`), rutas autenticadas en
  `routes/web.php` (respuestas binarias `Response`, nunca `StreamedResponse`,
  cabeceras `X-Content-Type-Options: nosniff` y `Cache-Control: private,
  no-store`), añadir controladores y middleware a `MIGRATED_CONTROLLERS`, y
  entrada «Documentos» en `Workspace` (`Menu`).
  - Criterios: DOC-R01.1 – R06.3, DOC-R08.2, DOC-R08.3, DOC-R08.4, DOC-R09.1,
    DOC-R09.2, DOC-R10.2, DOC-R10.3, DOC-NF01, DOC-NF04
  - Tests:
    - `tests/Feature/Documents/ReportCardsHttpTest.php` › «issues term, final and batch report cards over HTTP with immutability after grade edits»
    - `tests/Feature/Documents/CertificatesHttpTest.php` › «issues all five certificates, handles missing assets notice, re-downloads and voids over HTTP»
    - `tests/Feature/Documents/DocumentPermissionsHttpTest.php` › «enforces emitir-documentos, Coordinator grade scope and menu visibility»
  - Hecho cuando: todos los flujos HTTP, permisos, inmutabilidad y menú están
    en verde.

- [ ] **T-14** — Pantallas Inertia/Vue y accesibilidad:
  `resources/js/Pages/Documents/Index.vue` (historial con filtros, aviso de
  faltantes, emisión en lote por sección, descarga y anulación con modal),
  accesos directos en `Pages/Students/Show.vue` y vista de sección, más tests
  de navegador con axe en escritorio y 360 px.
  - Criterios: DOC-R05.3, DOC-R05.5, DOC-R08.4, DOC-NF06
  - Test: `tests/Browser/DocumentsTest.php` › «documents index and public QR verification pass axe accessibility checks at desktop and 360px»
  - Hecho cuando: `npm run build` compila limpio y las vistas cumplen WCAG 2.1
    AA en escritorio y 360 px.

- [ ] **T-15** — Aislamiento real con RLS/PgBouncer y pruebas de rendimiento:
  - `tests/Isolation/DocumentIsolationTest.php` (`--group=rls`): dos planteles
    reales, verificación cruzada bloqueada por RLS y escritura bloqueada por
    `SET TRANSACTION READ ONLY` en plantel inactivo (PostgreSQL y PgBouncer);
  - `tests/Feature/Documents/DocumentPerformanceTest.php` (`--group=perf`):
    emisión individual < 2 s (p95), verificación QR < 200 ms (p95) y lote de
    40 boletas < 15 s (p95).
  - Criterios: DOC-R07.4, DOC-R07.5, DOC-R10.1, DOC-R10.2, DOC-R10.3, DOC-NF05
  - Tests:
    - `tests/Isolation/DocumentIsolationTest.php` › «isolates documents across institutions and enforces read-only verification on inactive institutions»
    - `tests/Feature/Documents/DocumentPerformanceTest.php` › «meets latency budgets for single issuance, public QR verification and 40-student batch»
  - Hecho cuando: `--group=rls` (directo y PgBouncer) y `--group=perf` están en
    verde.

- [ ] **T-16** — Cierre documental y verificación final:
  - actualizar `specs/001-tenancy/requirements.md` (`TEN-R03.3`),
    `specs/002-identidad-acceso/requirements.md` (`IAM-R11.1`) y
    `specs/013-layout-menu/requirements.md` (`NAV-R03`);
  - completar la matriz de trazabilidad de `specs/012-documentos/requirements.md`
    (48/48 criterios);
  - actualizar `estado: implementada` en `requirements.md`, `design.md`,
    `tasks.md`, `specs/README.md` y `specs/roadmap.md`;
  - ejecutar `/security-review` de SPEC-012 (S-12).
  - Criterios: DOC-NF01 – NF06
  - Test: `tests/Architecture/SpecsTest.php` y `tests/Architecture/LayersTest.php`
  - Hecho cuando: toda la verificación final de abajo está marcada.

---

## Verificación final

- [ ] Suite completa en verde (`php artisan test --compact --exclude-group=rls,perf`,
  `--group=rls` y `--group=perf`).
- [ ] Suite `--group=rls` en verde contra PgBouncer (`phpunit.pgbouncer.xml`).
- [ ] Tests de arquitectura (`tests/Architecture/SpecsTest.php` y `LayersTest.php`)
  en verde.
- [ ] `vendor/bin/pint --format agent` y `composer audit --locked` sin errores.
- [ ] `migrate:fresh --seed`, `migrate:reset` y `migrate` en verde (incluyendo
  `up`/`down` de `grant_issue_documents_permission`).
- [ ] Matriz de trazabilidad de `specs/012-documentos/requirements.md` completa
  (48/48 criterios).
- [ ] `/security-review` de SPEC-012 completado (toca permisos, tenancy/RLS y
  endpoint público; S-12).
- [ ] `estado` de `requirements.md`, `design.md`, `tasks.md`, `specs/README.md`
  y `specs/roadmap.md` actualizados a `implementada`.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| DOC-R01 | T-06, T-07, T-13 |
| DOC-R02 | T-06, T-07, T-13 |
| DOC-R03 | T-08, T-13 |
| DOC-R04 | T-03, T-09, T-13 |
| DOC-R05 | T-03, T-04, T-05, T-06, T-07, T-08, T-09, T-10, T-13, T-14 |
| DOC-R06 | T-03, T-10, T-13 |
| DOC-R07 | T-03, T-05, T-06, T-07, T-08, T-09, T-11, T-12, T-15 |
| DOC-R08 | T-02, T-03, T-06, T-07, T-08, T-09, T-10, T-13, T-14 |
| DOC-R09 | T-07, T-08, T-09, T-10, T-13 |
| DOC-R10 | T-04, T-05, T-07, T-10, T-12, T-13, T-15 |
| DOC-NF01 | T-06, T-07, T-08, T-09, T-10, T-13, T-16 |
| DOC-NF02 | T-03, T-07, T-08, T-09, T-16 |
| DOC-NF03 | T-12, T-16 |
| DOC-NF04 | T-01, T-05, T-13, T-16 |
| DOC-NF05 | T-15, T-16 |
| DOC-NF06 | T-12, T-14, T-16 |
