---
spec: 012-documentos
estado: borrador
fecha: 2026-11-27
requisitos: requirements.md
adr: ../adr/0015-documentos-pdf-inmutables-y-verificacion-qr.md
---

# Diseño — Documentos del plantel y verificación QR

## Resumen

Se crea el nuevo módulo `src/Documents/` (arquitectura hexagonal, `A-01` –
`A-09`) para emitir, conservar de forma inmutable, listar, re-descargar, anular
y verificar públicamente por QR las boletas (por lapso, finales y en lote) y
constancias del plantel. El módulo consume únicamente la capa `Application` de
`Tenancy`, `AcademicCalendar`, `AcademicStructure`, `Students`, `Enrollment`,
`Evaluation`, `Audit` e `IdentityAccess` (`A-06`), usa `dompdf/dompdf` +
`bacon/bacon-qr-code` detrás de puertos dedicados y guarda cada PDF en disco
privado del plantel más su registro inmutable con RLS en PostgreSQL
([ADR 0015](../adr/0015-documentos-pdf-inmutables-y-verificacion-qr.md)).

## Módulo y capas

```
src/Documents/
├── Domain/
│   ├── DocumentId.php                    (VO bigint)
│   ├── DocumentKind.php                  (enum: term_report, final_report, study_certificate, enrollment_certificate, withdrawal_certificate, good_conduct_certificate, progression_certificate)
│   ├── DocumentStatus.php                (enum: valid, expired, voided)
│   ├── VerificationCode.php              (VO 128-bit opaco, normalización, hash SHA-256, codeHint)
│   ├── StudentPublicSummary.php          (VO: iniciales + últimos 4 dígitos de cédula/cédula escolar)
│   ├── ValidityPeriod.php                (VO: días 1..365 para constancias temporales; null para permanentes)
│   ├── GoodConductNote.php               (VO: texto plano opcional 0..300 caracteres)
│   ├── VoidReason.php                    (VO: motivo obligatorio 5..500 caracteres)
│   ├── IssuedDocument.php                (Agregado raíz: issue, void, statusAt, isDownloadable)
│   ├── IssuedDocumentRepository.php      (Puerto de dominio)
│   └── DocumentScopePolicy.php           (Servicio de dominio: verificación de alcance por grados de Coordinador)
├── Application/
│   ├── Port/
│   │   ├── DocumentPermissions.php       (permiso emitir-documentos, rol coordinador/directivo, actorId)
│   │   ├── DocumentStorage.php           (guardar/leer PDF privado por plantel)
│   │   ├── PdfRenderer.php               (renderizar boleta, lote o constancia a bytes PDF)
│   │   ├── QrCodeGenerator.php           (generar imagen QR data-URI para una URL)
│   │   ├── InstitutionFacts.php          (perfil, DEA, RIF, ubicación, logo, firmas, faltantes)
│   │   ├── AcademicFacts.php             (año escolar, lapsos, secciones, pensum, grados coordinados)
│   │   ├── StudentRecordFacts.php        (estudiante, inscripciones, retiros, resultado prosecución/egreso)
│   │   ├── EvaluationFacts.php           (boleta de lapso/final: notas, revisiones, pendientes, literales, informes)
│   │   └── DocumentHistoryDirectory.php  (lecturas CQRS paginadas/filtradas del historial)
│   ├── DTO/
│   │   ├── IssuedDocumentView.php
│   │   ├── PublicVerificationView.php
│   │   ├── DownloadablePdf.php
│   │   ├── ReportCardData.php
│   │   └── CertificateData.php
│   ├── DocumentAccess.php                (guardia S-04: emitir-documentos + DocumentScopePolicy)
│   ├── DocumentAudit.php                 (payloads de bitácora sin binarios ni código completo)
│   ├── DocumentNotFound.php              (subclase de NotFound)
│   ├── IssueTermReport/
│   │   ├── IssueTermReportCommand.php
│   │   └── IssueTermReportHandler.php
│   ├── IssueFinalReport/
│   │   ├── IssueFinalReportCommand.php
│   │   └── IssueFinalReportHandler.php
│   ├── IssueSectionBatch/
│   │   ├── IssueSectionBatchCommand.php
│   │   └── IssueSectionBatchHandler.php
│   ├── IssueCertificate/
│   │   ├── IssueCertificateCommand.php
│   │   └── IssueCertificateHandler.php
│   ├── DownloadDocument/
│   │   └── DownloadDocumentQuery.php
│   ├── VoidDocument/
│   │   ├── VoidDocumentCommand.php
│   │   └── VoidDocumentHandler.php
│   ├── ListDocuments/
│   │   ├── ListDocumentsQuery.php
│   │   └── ListStudentDocumentsQuery.php
│   └── VerifyDocument/
│       └── VerifyDocumentQuery.php
└── Infrastructure/
    ├── DocumentsServiceProvider.php
    ├── GateDocumentPermissions.php
    ├── Persistence/
    │   ├── IssuedDocumentModel.php
    │   ├── IssuedDocumentMapper.php
    │   ├── EloquentIssuedDocumentRepository.php
    │   └── EloquentDocumentHistoryDirectory.php
    ├── Storage/
    │   └── LaravelDocumentStorage.php
    ├── Pdf/
    │   ├── DompdfRenderer.php
    │   └── BaconQrCodeGenerator.php
    └── Facts/
        ├── TenancyInstitutionFacts.php
        ├── SchoolAcademicFacts.php
        ├── SchoolStudentRecordFacts.php
        └── SchoolEvaluationFacts.php
```

### Ajustes mínimos en otros módulos (solo contratos públicos `Application` / configuración)

- **`IdentityAccess`:** añadir `Permission::IssueDocuments = 'emitir-documentos'`
  y asignarlo por defecto a `BaseRole::Administrador`, `BaseRole::Director` y
  `BaseRole::Coordinador` (con migración idempotente que lo concede en
  planteles existentes, `DOC-R08.1`).
- **`Tenancy`:**
  - añadir `ResolveTenantForVerificationHandler` (en `Application/ResolveTenant/`)
    que resuelve un subdominio válido esté activo o inactivo, únicamente usado
    por el middleware `ResolveTenantForVerification` (`DOC-R07.5`);
  - exponer `InstitutionProfileQuery` (en `Application/InstitutionSettings/`)
    con los datos del encabezado (nombre, DEA, RIF, ubicación textual,
    dirección, teléfono, correo, nombre/cargo de firmas y avisos de faltantes)
    sin requerir `gestionar-plantel` porque es consumo interno de otro módulo
    (`A-06`), reutilizando `InstitutionAssetsQuery` para logo y firmas
    (`INS-R04.7`);
  - añadir `TenantActivator::forReadOnlyRequest()` en `Tenancy` /
    `PostgresTenantActivator` que ejecuta `SET TRANSACTION READ ONLY` antes de
    activar la RLS y cierra la transacción con `rollBack()` al terminar.
- **`Evaluation`:** exponer la query pública de lectura
  `StudentReportCardQuery` (y `SectionReportCardsQuery` para lotes sin N+1) en
  `src/Evaluation/Application/ReportCard/`, devolviendo DTOs puros con notas de
  lapso, definitiva, nota de revisión, definitiva tras revisión, materias
  pendientes cursadas en el año, literales/descripciones de Primaria e informes
  descriptivos de Inicial.
- **`Enrollment` & `Students`:** reutilizar `ShowStudentQuery`,
  `StudentHistoryQuery`, `SectionRosterQuery` y `PromotionSheetQuery` (o
  exponer una query ligera `StudentWithdrawalQuery` en `Students` /
  `StudentEnrollmentOutcomeQuery` en `Enrollment` si la firma actual pide
  permisos de otro módulo, evitando acoplar `emitir-documentos` con
  `ver-estudiantes` si se asigna persona a persona).
- **`Workspace`:** añadir la entrada «Documentos» en el grupo «Estudiantes» del
  menú lateral para quien tenga `emitir-documentos` (`DOC-R08.4`).
- **`tests/Architecture/LayersTest.php`:**
  - actualizar `no module depends on Evaluation` para que compruebe que los
    módulos *base* (`Shared`, `Tenancy`, `IdentityAccess`, `Catalogs`, `Audit`,
    `AcademicCalendar`, `AcademicStructure`, `Students`, `Enrollment`,
    `Platform`) no dependan de `Evaluation`, permitiendo que `Documents` (y
    futuros consumidores de lectura) consuman `AulaX\Evaluation\Application`;
  - añadir regla estática que confine `ResolveTenantForVerificationHandler`
    exclusivamente al middleware `ResolveTenantForVerification`;
  - añadir los controladores y middleware nuevos a `MIGRATED_CONTROLLERS`.

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `IssuedDocument` | Agregado raíz | Constructor privado; `issue(...)`, `reconstitute(...)`, `void(VoidReason, int $actorId, DateTimeImmutable $at)`. Inmutable en datos de emisión, PDF y hash. No permite anular dos veces. Calcula `statusAt(DateTimeImmutable $now): DocumentStatus` (`voided` > `expired` > `valid`) e `isDownloadable(): bool` (`! isVoided()`). |
| `DocumentId` | Value Object | Entero positivo (`bigint`, `C-04`). |
| `DocumentKind` | Enum | `term_report`, `final_report`, `study_certificate`, `enrollment_certificate`, `withdrawal_certificate`, `good_conduct_certificate`, `progression_certificate`. Sabe si el tipo tiene vigencia temporal (`hasExpiration()`: `true` solo para estudio, inscripción y buena conducta) y su etiqueta en español. |
| `DocumentStatus` | Enum | `valid`, `expired`, `voided`. |
| `VerificationCode` | Value Object | Genera 16 bytes aleatorios (`random_bytes(16)` = 128 bits, `DOC-NF02`), codificados en base32 Crockford / URL-safe sin caracteres ambiguos (26 caracteres, agrupados visualmente en el PDF p. ej. `XXXX-XXXX-…`). Expone `hash()` (SHA-256 hex de 64 chars) y `hint()` (últimos 6 caracteres, no invertible). `fromRaw()` normaliza guiones/espacios/mayúsculas para entrada manual. |
| `StudentPublicSummary` | Value Object | Extrae iniciales de nombres y apellidos (`A. M. P. G.`) y los últimos 4 dígitos de la cédula o cédula escolar (`****1234`, o `—` si tiene menos de 4 dígitos). Garantiza por dominio `DOC-R07.2` y `DOC-R07.3`. |
| `ValidityPeriod` | Value Object | Entero entre 1 y 365 días (por defecto 90). Lanza `BusinessRuleViolation` fuera de rango (`DOC-R04.6`). |
| `GoodConductNote` | Value Object | Recorta espacios; si queda vacío es `null`; si excede 300 caracteres (mb_strlen) o contiene caracteres de control no permitidos lanza `BusinessRuleViolation` (`DOC-R04.4`, `DOC-R04.6`). |
| `VoidReason` | Value Object | Recorta espacios; obligatorio entre 5 y 500 caracteres (`DOC-R06.1`, `DOC-R06.2`). |
| `DocumentScopePolicy` | Servicio de dominio | Dado si el actor tiene alcance total (`Administrador`/`Director` o miembro con `emitir-documentos` sin restricción de Coordinador) o es `Coordinador` con `list<string> $coordinatedGrades`, decide si puede operar sobre un grado concreto (`DOC-R08.3`). |

## Casos de uso (Application)

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `IssueTermReportHandler` | `IssueTermReportCommand(studentId, schoolYearId, sectionId, term)` | `DownloadablePdf` (+ `documentId`) | DOC-R01.1 – R01.6, R05.1 – R05.4, R07.1, R08.2, R08.3, R09.1, R09.2, R10.1 – R10.3, NF01, NF02, NF04, NF05 |
| `IssueFinalReportHandler` | `IssueFinalReportCommand(studentId, schoolYearId, sectionId)` | `DownloadablePdf` (+ `documentId`) | DOC-R02.1 – R02.4, R05.1 – R05.4, R07.1, R08.2, R08.3, R09.1, R09.2, R10.1 – R10.3, NF01, NF02, NF04, NF05 |
| `IssueSectionBatchHandler` | `IssueSectionBatchCommand(schoolYearId, sectionId, ?term)` | `DownloadablePdf` (PDF consolidado + conteo) | DOC-R03.1 – R03.4, R05.1 – R05.4, R07.1, R08.2, R08.3, R09.1, R09.2, R10.1 – R10.3, NF01, NF02, NF04, NF05 |
| `IssueCertificateHandler` | `IssueCertificateCommand(studentId, kind, ?schoolYearId, ?validityDays, ?goodConductNote)` | `DownloadablePdf` (+ `documentId`) | DOC-R04.1 – R04.6, R05.1 – R05.4, R07.1, R08.2, R08.3, R09.1, R09.2, R10.1 – R10.3, NF01, NF02, NF04, NF05 |
| `DownloadDocumentQuery` | `documentId` | `DownloadablePdf` | DOC-R05.4, R05.5, R06.3, R08.2, R08.3, R10.1 – R10.3, NF01, NF04 |
| `VoidDocumentHandler` | `VoidDocumentCommand(documentId, reason)` | `void` | DOC-R06.1 – R06.3, R08.2, R08.3, R09.1, R10.1 – R10.3, NF01 |
| `ListDocumentsQuery` | `filters(kind, schoolYearId, status, search, page)` | lista paginada de `IssuedDocumentView` + avisos de faltantes del plantel | DOC-R05.3, R05.5, R08.2, R08.3, R10.1, R10.2, NF01 |
| `ListStudentDocumentsQuery` | `studentId` | `list<IssuedDocumentView>` + opciones elegibles + avisos de faltantes | DOC-R05.3, R05.5, R08.2 – R08.4, R10.1 – R10.3, NF01 |
| `VerifyDocumentQuery` | `rawCode` | `?PublicVerificationView` | DOC-R07.2 – R07.5, R10.1, R10.2, NF03, NF05, NF06 |

### Orden de escritura en disco vs transacción de BD (consistencia)

En `IssueTermReportHandler`, `IssueFinalReportHandler`, `IssueCertificateHandler` e `IssueSectionBatchHandler`:
1. Validar permisos, alcance de grados (`DocumentScopePolicy`), reglas de elegibilidad y datos de entrada (antes de renderizar).
2. Generar el código de verificación (`VerificationCode::generate()`), el QR y renderizar los bytes del PDF en memoria.
3. Dentro de `$transactions->run(...)`: guardar cada `IssuedDocument` en `issued_documents` y registrar el evento de auditoría en `AuditTrail`.
4. Almacenar los bytes del PDF en `DocumentStorage`:
   - El archivo se escribe en disco justo antes de confirmar la transacción (o dentro del callback con limpieza en `catch (Throwable)` si la transacción falla), garantizando que nunca quede una fila en `issued_documents` apuntando a un archivo inexistente ni archivos huérfanos en caso de rollback.
   - En `IssueSectionBatchHandler`, todos los registros individuales y el evento único de bitácora del lote se persisten en la misma transacción; si ocurre un fallo a mitad del lote, se revierten todos los registros y se eliminan los PDFs recién escritos.

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `IssuedDocumentRepository` | Domain | `EloquentIssuedDocumentRepository` | Guarda y busca por `DocumentId` y por `verification_code_hash` bajo RLS. |
| `DocumentPermissions` | Application | `GateDocumentPermissions` | Lee `emitir-documentos`, si es `Administrador`/`Director`/`Coordinador` y el `accountId`. |
| `DocumentStorage` | Application | `LaravelDocumentStorage` | Escribe/lee/borra `institutions/{id}/documents/{random40}.pdf` con regex estricta `isOwn()` igual que `LaravelInstitutionFiles` (`INS-R07.2`, `DOC-R10.1`). |
| `PdfRenderer` | Application | `DompdfRenderer` | Renderiza vistas Blade escapadas a PDF con `dompdf` (`isRemoteEnabled=false`, `isPhpEnabled=false`). |
| `QrCodeGenerator` | Application | `BaconQrCodeGenerator` | Reutiliza `bacon/bacon-qr-code` para producir SVG/PNG data-URI en memoria. |
| `InstitutionFacts` | Application | `TenancyInstitutionFacts` | Llama a `InstitutionProfileQuery` y `InstitutionAssetsQuery` de `Tenancy`. |
| `AcademicFacts` | Application | `SchoolAcademicFacts` | Llama a queries públicas de `AcademicCalendar` y `AcademicStructure`. |
| `StudentRecordFacts` | Application | `SchoolStudentRecordFacts` | Llama a queries públicas de `Students` y `Enrollment`. |
| `EvaluationFacts` | Application | `SchoolEvaluationFacts` | Llama a `StudentReportCardQuery` y `SectionReportCardsQuery` de `Evaluation`. |
| `DocumentHistoryDirectory` | Application | `EloquentDocumentHistoryDirectory` | Query service CQRS (`A-08`) para listado filtrado/paginado del historial bajo RLS. |

## Adaptadores de entrada (`app/`)

### Rutas en `{tenant}.{APP_DOMAIN}` (`routes/web.php`)

1. **Ruta pública de verificación QR** *(fuera del grupo `ResolveTenant`, usando `ResolveTenantForVerification` + `throttle:document-verification`, sin `auth`)*:
   - `GET /verificar/{code}` → `DocumentVerificationController@show` (`documents.verify`) → `VerifyDocumentQuery`.
   - Renderiza página Inertia pública minimalista (`Pages/Documents/Verify.vue`, móvil 360 px, WCAG 2.1 AA, sin barra lateral ni sesión).
2. **Rutas autenticadas del plantel** *(dentro del grupo `ResolveTenant` + `auth` + `active.membership` + `can:emitir-documentos`)*:
   - `GET /documentos` → `DocumentController@index` (`documents.index`) → `ListDocumentsQuery`.
   - `POST /estudiantes/{student}/documentos/boleta-lapso` → `DocumentController@issueTermReport` (`documents.term-report`) → `IssueTermReportHandler`.
   - `POST /estudiantes/{student}/documentos/boleta-final` → `DocumentController@issueFinalReport` (`documents.final-report`) → `IssueFinalReportHandler`.
   - `POST /estudiantes/{student}/documentos/constancia` → `DocumentController@issueCertificate` (`documents.certificate`) → `IssueCertificateHandler`.
   - `POST /secciones/{section}/documentos/boletas` → `DocumentController@issueSectionBatch` (`documents.section-batch`) → `IssueSectionBatchHandler`.
   - `GET /documentos/{document}/descargar` → `DocumentController@download` (`documents.download`) → `DownloadDocumentQuery`.
   - `POST /documentos/{document}/anular` → `DocumentController@void` (`documents.void`) → `VoidDocumentHandler`.
   - Todos los endpoints de descarga devuelven `response($pdf->bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="..."', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])` — **nunca `StreamedResponse`** (ADR 0011).

### Rate limiting (`AppServiceProvider`)

- Limiter `document-verification`: `Limit::perMinute(30)->by(strtolower((string) $request->route('tenant')).'|'.$request->ip())` (`DOC-NF03`, `S-06`).

### FormRequests (validación de forma únicamente, `S-09`)

- `IssueTermReportRequest` (`school_year_id`, `section_id`, `term`)
- `IssueFinalReportRequest` (`school_year_id`, `section_id`)
- `IssueSectionBatchRequest` (`school_year_id`, `term` nullable)
- `IssueCertificateRequest` (`kind`, `school_year_id` nullable, `validity_days` nullable int, `observation` nullable string)
- `VoidDocumentRequest` (`reason` required string)

### Integración UI (Inertia / Vue)

- Página `Pages/Documents/Index.vue`: historial filtrable (tipo, año escolar, estado), aviso de faltantes del plantel (`DOC-R05.3`), acceso a emisión en lote por sección, re-descarga y modal de anulación con motivo.
- Bloque «Documentos» en la ficha del estudiante (`Pages/Students/Show.vue`) y botón «Emitir boletas de la sección» en la vista de sección (`DOC-R08.4`), visibles solo cuando el usuario tiene `emitir-documentos`.
- Página pública `Pages/Documents/Verify.vue`: diseño limpio para móvil (360 px), indicador claro de estado (`Válido`, `Vencido`, `Anulado`, o `Documento no encontrado`), sin navegación autenticada ni PII prohibida.

## Datos

### Migración `create_issued_documents_table`

```sql
issued_documents
- id                         bigserial PK
- institution_id             bigint NOT NULL (belongsToInstitution() + RowLevelSecurity::enable(), S-01)
- student_id                 bigint NOT NULL FK → students(id) ON DELETE RESTRICT
- school_year_id             bigint NULL FK → school_years(id) ON DELETE RESTRICT
- section_id                 bigint NULL FK → sections(id) ON DELETE RESTRICT
- grade_code                 varchar(40) NOT NULL          -- para filtro de Coordinador sin joins frágiles
- kind                       varchar(40) NOT NULL CHECK (kind IN (...7 valores...))
- term                       smallint NULL CHECK (term IS NULL OR term IN (1, 2, 3))
- verification_code_hash     char(64) NOT NULL
- code_hint                  varchar(12) NOT NULL          -- sufijo visible en historial (no invertible)
- pdf_path                   varchar(255) NOT NULL
- pdf_sha256                 char(64) NOT NULL
- pdf_bytes                  integer NOT NULL CHECK (pdf_bytes > 0)
- student_full_name_snapshot varchar(255) NOT NULL         -- para historial interno del plantel
- student_initials           varchar(32) NOT NULL          -- público QR
- student_id_last4           varchar(8) NOT NULL           -- público QR
- institution_name_snapshot  varchar(255) NOT NULL         -- público QR
- institution_dea_snapshot   varchar(20) NULL              -- público QR
- period_label               varchar(120) NOT NULL         -- ej. "2026-2027 · 1.er lapso" o "2026-2027"
- issued_by                  bigint NOT NULL FK → users(id) ON DELETE RESTRICT
- issued_at                  timestamp NOT NULL
- expires_at                 timestamp NULL CHECK (expires_at IS NULL OR expires_at > issued_at)
- voided_at                  timestamp NULL
- voided_by                  bigint NULL FK → users(id) ON DELETE RESTRICT
- void_reason                varchar(500) NULL
- created_at / updated_at    timestamp
```

- **Índices:**
  - `UNIQUE (institution_id, verification_code_hash)` — garantiza unicidad y verificación QR O(log N) bajo RLS.
  - `INDEX (institution_id, student_id, issued_at DESC)` — historial en ficha del estudiante.
  - `INDEX (institution_id, kind, school_year_id, issued_at DESC)` — filtros en pantalla Documentos.
  - `INDEX (institution_id, grade_code, issued_at DESC)` — filtro eficiente para Coordinadores.
- **Restricciones de coherencia (`CHECK`):**
  - `(voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL) OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL)`
  - `(kind = 'term_report' AND term IS NOT NULL) OR (kind <> 'term_report' AND term IS NULL)`
  - `(kind IN ('study_certificate', 'enrollment_certificate', 'good_conduct_certificate') AND expires_at IS NOT NULL) OR (kind NOT IN ('study_certificate', 'enrollment_certificate', 'good_conduct_certificate') AND expires_at IS NULL)`
- **RLS:** `belongsToInstitution()` + `RowLevelSecurity::enable('issued_documents')` (`S-01`). Cubierta automáticamente por `TenantTableTest`.

### Migración de permisos `grant_issue_documents_permission`

- Crea el permiso `emitir-documentos` y lo asigna a los roles `Administrador`,
  `Director` y `Coordinador` de cada institución existente (idempotente; en
  planteles nuevos lo siembra `RolePermissionSeeder` desde `BaseRole`).

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| **Aggregate (`IssuedDocument`)** | Protege las invariantes de emisión, vigencia por tipo, inmutabilidad y anulación irreversible en un solo lugar del dominio. |
| **Value Objects (`VerificationCode`, `StudentPublicSummary`, `ValidityPeriod`, `GoodConductNote`, `VoidReason`)** | Impide estados inválidos (códigos cortos/predecibles, vigencia fuera de 1–365, fuga de PII en el resumen público). |
| **Ports & Adapters (`PdfRenderer`, `QrCodeGenerator`, `DocumentStorage`, `*Facts`)** | Aísla `dompdf`, `bacon-qr-code`, almacenamiento en disco y queries de otros 5 módulos para poder probar 100 % del dominio y la aplicación en memoria en milisegundos (`Q-01`, `A-06`). |
| **CQRS ligero (`DocumentHistoryDirectory`, `VerifyDocumentQuery`)** | Listados paginados/filtrados y verificación pública responden con DTOs livianos sin hidratar agregados ni leer PDFs de disco (`A-08`, `DOC-NF05`). |
| **Domain Service (`DocumentScopePolicy`)** | Centraliza el alcance por grados del `Coordinador` en todos los casos de uso de emisión, historial, descarga y anulación (`S-04`). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Falsificar una boleta/constancia con un QR inventado o reutilizado de otro plantel. | Códigos de 128 bits (`random_bytes(16)`), almacenados solo como hash SHA-256 por plantel (`UNIQUE (institution_id, verification_code_hash)`). La verificación resuelve el plantel del subdominio (`S-02`) con RLS (`S-01`). | `VerificationCodeTest`, `DocumentVerificationTest`, `DocumentIsolationTest` |
| **T**ampering | Alterar notas, firmas o logo después de emitir para cambiar un PDF ya entregado, o modificar la ruta `pdf_path` para leer archivos de otro plantel. | PDF binario inmutable guardado en disco privado + `pdf_sha256`; `LaravelDocumentStorage::isOwn()` valida prefijo `institutions/{id}/documents/` y patrón estricto sin `..`. La verificación en plantel inactivo usa `SET TRANSACTION READ ONLY` en PostgreSQL. | `DocumentImmutabilityTest`, `DocumentStorageTest`, `DocumentIsolationTest` |
| **R**epudiation | Emitir o anular documentos sin rastro de quién lo hizo ni por qué. | Toda emisión individual, emisión en lote y anulación se registra en `AuditTrail` dentro de la misma transacción (`ADR 0005`, `DOC-R09.1`). Las anulaciones no borran la fila. | `DocumentAuditTest`, `VoidDocumentTest` |
| **I**nformation disclosure | (a) Exponer nombre completo, cédula completa, notas, observación o motivo de anulación en el QR público. (b) Enumerar códigos QR o saber si un código existe en otro plantel. (c) Fuga de datos de otro plantel o fuera de grados del Coordinador. (d) Revelar si un plantel está inactivo en el QR. | (a) `VerifyDocumentQuery` lee solo columnas públicas precalculadas (`StudentPublicSummary`, sin notas/observación/motivo). (b) Código opaco de 128 bits + mismo mensaje «Documento no encontrado» + rate limit 30/min por plantel e IP. (c) RLS + `DocumentScopePolicy` devuelve 404 fuera de grados. (d) `ResolveTenantForVerification` no expone estado comercial. (e) Respuesta de descarga con `Cache-Control: private, no-store` y `X-Content-Type-Options: nosniff`. | `DocumentVerificationTest`, `DocumentScopeTest`, `DocumentIsolationTest`, `DocumentVerificationThrottleTest` |
| **D**enial of service | (a) Fuerza bruta sobre `/verificar/{code}`. (b) Abuso de emisión en lote o SSRF vía HTML/CSS dentro de `dompdf`. | (a) Rate limiter `document-verification` (30 req/min por plantel+IP, `DOC-NF03`). (b) `isRemoteEnabled = false`, `isPhpEnabled = false`, escape Blade estricto (`{{ }}`), validación de tamaño de sección elegible y verificación de tiempo en `DocumentPerformanceTest` (`DOC-NF05`). | `DocumentVerificationThrottleTest`, `DompdfRendererTest`, `DocumentPerformanceTest` |
| **E**levation of privilege | (a) Usuario sin `emitir-documentos` (p. ej. Docente o Representante) intenta emitir/descargar/anular. (b) Coordinador intenta emitir/anular para un grado que no coordina. (c) Intento de escritura en plantel inactivo a través de `/verificar/{code}`. | (a) Doble barrera `can:emitir-documentos` en ruta + `DocumentAccess` en caso de uso (`S-04`). (b) `DocumentScopePolicy` en cada handler. (c) `ResolveTenantForVerification` solo en ruta `GET` y con `SET TRANSACTION READ ONLY` en PostgreSQL (`RSP-R08`). | `DocumentAuthorizationTest`, `DocumentScopeTest`, `InactiveTenantVerificationTest`, `LayersTest` |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `AuthorizationDenied` | 403 | «No tienes permiso para emitir ni gestionar documentos.» |
| `DocumentNotFound` (subclase de `NotFound`) | 404 | «Ese documento no existe.» (también cuando el estudiante/sección/documento pertenece a otro plantel o está fuera de los grados del Coordinador) |
| `InstitutionNotFound` (en `/verificar/{code}` con subdominio inexistente) | 404 | Página 404 estándar |
| `BusinessRuleViolation` (lapso abierto o sin abrir) | 422 | «Solo se pueden emitir boletas de lapsos cerrados.» / «La boleta final solo se emite cuando el tercer lapso está cerrado.» |
| `BusinessRuleViolation` (sin inscripción válida / sin retiro / sin resultado / pendiente de egresar) | 422 | Mensaje específico por tipo de constancia/boleta (p. ej. «El estudiante no tiene una inscripción vigente en el año escolar activo.», «El estudiante aún tiene materias pendientes por aprobar para egresar.») |
| `BusinessRuleViolation` (vigencia fuera de 1–365 o observación > 300) | 422 | «La vigencia debe estar entre 1 y 365 días.» / «La observación no puede superar los 300 caracteres.» |
| `BusinessRuleViolation` (lote sin estudiantes elegibles) | 422 | «La sección no tiene estudiantes elegibles para emitir boletas en este período.» |
| `BusinessRuleViolation` (anulación inválida o ya anulado / descarga de anulado) | 422 | «Indica un motivo de entre 5 y 500 caracteres.» / «Ese documento ya fue anulado.» |

## Estrategia de pruebas

- **Dominio (unit, `tests/Unit/Documents/Domain/`):** invariantes de
  `IssuedDocument`, `VerificationCode` (entropía, normalización, hash, hint),
  `StudentPublicSummary` (iniciales y últimos 4 dígitos en distintos formatos
  de cédula/cédula escolar), `ValidityPeriod`, `GoodConductNote`, `VoidReason`
  y `DocumentScopePolicy`.
- **Aplicación (unit, adaptadores en memoria, `tests/Unit/Documents/Application/`):**
  cada handler (`IssueTermReportHandler`, `IssueFinalReportHandler`,
  `IssueSectionBatchHandler`, `IssueCertificateHandler`, `DownloadDocumentQuery`,
  `VoidDocumentHandler`, `ListDocumentsQuery`, `VerifyDocumentQuery`) con
  `FakePdfRenderer`, `InMemoryDocumentStorage`, `InMemoryIssuedDocumentRepository`
  y `RecordingAuditTrail` — probando todas las reglas de negocio, rollback de
  archivos si falla la persistencia, Coordinador fuera de grados y bitácora sin
  PII/códigos completos.
- **Infraestructura (integración contra PostgreSQL real, `tests/Feature/Documents/Infrastructure/`):**
  `EloquentIssuedDocumentRepositoryTest` (constraints `CHECK`, unicidad de hash,
  RLS), `LaravelDocumentStorageTest` (aislamiento de carpetas y `isOwn()`),
  `DompdfRendererTest` (genera PDF válido `%PDF-`, escapa `<script>` / `<img>`
  en nombres/informes/observaciones sin peticiones remotas),
  `EvaluationReportCardQueryTest` (notas, NP/NA, revisiones, pendientes,
  literales e informes).
- **Feature (HTTP de punta a punta, `tests/Feature/Documents/`):**
  boletas por lapso y finales (Inicial, Primaria, Media con revisión y
  pendientes), emisión en lote por sección, las 5 constancias, faltantes de
  logo/firmas/DEA con aviso, inmutabilidad tras editar una nota en lapso
  cerrado, anulación, verificación pública QR (válido/vencido/anulado/inexistente,
  sin PII, throttle 429, plantel inactivo en solo lectura y sin permitir
  escrituras), permisos y alcance del Coordinador, entrada en menú.
- **Isolation / RLS (`tests/Isolation/DocumentIsolationTest.php`, `--group=rls` y PgBouncer):**
  dos planteles reales; RLS impide leer/descargar/verificar documentos de otro
  plantel; `SET TRANSACTION READ ONLY` en plantel inactivo rechaza escrituras a
  nivel PostgreSQL.
- **Rendimiento (`tests/Feature/Documents/DocumentPerformanceTest.php`, `--group=perf`):**
  emisión individual < 2 s (p95), verificación QR < 200 ms (p95), lote de 40
  boletas < 15 s (p95) (`DOC-NF05`).
- **Navegador / accesibilidad (`tests/Browser/DocumentsTest.php`, `--group=browser`):**
  `Documents/Index` y `Documents/Verify` en escritorio y 360 px sin violaciones
  WCAG 2.1 AA con axe (`DOC-NF06`).
- **Arquitectura (`tests/Architecture/LayersTest.php` y `SpecsTest.php`):**
  confinamiento de `ResolveTenantForVerificationHandler`, controladores en
  `MIGRATED_CONTROLLERS`, dependencia unidireccional hacia `Evaluation\Application`,
  y matriz de trazabilidad completa.

## Decisiones y alternativas

Ver [ADR 0015](../adr/0015-documentos-pdf-inmutables-y-verificacion-qr.md)
para el detalle de las tres decisiones principales:
1. `dompdf/dompdf` + `bacon/bacon-qr-code` frente a Chromium / `mpdf` / FPDF.
2. PDF binario inmutable + resumen público precalculado en `issued_documents`
   frente a regeneración desde JSON.
3. `ResolveTenantForVerification` con `SET TRANSACTION READ ONLY` frente a
   verificación en dominio central sin RLS.

Decisiones adicionales locales al diseño:
- **Una sola tabla `issued_documents` para boletas y constancias** (en lugar de
  dos tablas separadas): simplifica el historial unificado, la anulación, la
  verificación QR en una sola consulta indexada y la política RLS.
- **Un único `IssueCertificateHandler` parametrizado por `DocumentKind`** (en
  lugar de 5 handlers casi idénticos): comparte permisos, encabezado, firmas,
  generación QR, persistencia inmutable y auditoría, delegando las reglas
  específicas de elegibilidad de cada constancia en métodos claros y testeables.

## Riesgos y plan de rollback

- **Riesgo 1 — Peso/tiempo del lote de 40 boletas en `dompdf`:** un HTML con
  imágenes grandes repetidas (logo/firmas) puede ralentizar el render.
  *Mitigación:* reutilizar las data-URI ya codificadas en memoria una sola vez
  por lote y validar el tiempo real en `DocumentPerformanceTest` (< 15 s).
- **Riesgo 2 — Inyección HTML o SSRF en campos de texto libre** (informes
  descriptivos de Inicial de hasta 5.000 caracteres, descripciones de literales,
  observación de buena conducta): *Mitigación:* `isRemoteEnabled=false`,
  `isPhpEnabled=false`, escape Blade `{{ }}` y test dedicado en
  `DompdfRendererTest`.
- **Riesgo 3 — Fuga de archivos huérfanos en disco ante fallos de BD:**
  *Mitigación:* capturar excepciones de transacción y eliminar cualquier archivo
  escrito en ese intento.
- **Riesgo 4 — Dependencia nueva `dompdf/dompdf` en CI (`composer audit`):**
  *Mitigación:* instalar versión estable `^3.1` y ejecutar `composer audit
  --locked` en el mismo PR (`S-11`).
- **Plan de rollback:**
  - Las migraciones son 100 % aditivas (`create_issued_documents_table` con
    `down()` que borra la tabla; `grant_issue_documents_permission` con `down()`
    que revoca el permiso si se revierte). No alteran tablas existentes.
  - Si hubiera que deshabilitar `Documents` en producción, revertir el PR y
    correr rollback de migración deja el resto del sistema intacto (los archivos
    en `institutions/*/documents/` quedan inofensivos en el disco privado).
