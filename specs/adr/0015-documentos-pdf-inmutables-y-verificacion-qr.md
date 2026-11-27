---
adr: 0015
título: Generación de PDF, almacenamiento inmutable y verificación QR de documentos del plantel
estado: propuesta
fecha: 2026-11-27
spec: 012-documentos
---

# ADR 0015 — Generación de PDF, almacenamiento inmutable y verificación QR

## Contexto

SPEC-012 introduce el módulo `Documents` para emitir boletas (por lapso,
finales y en lote por sección) y constancias del plantel en PDF con logotipo,
firmas digitales y un código QR verificable públicamente (`DOC-R01` –
`DOC-R10`, `DOC-NF01` – `DOC-NF06`).

Tres decisiones tienen alternativas reales que condicionan el despliegue
(`docker/php/Dockerfile.prod` sobre `php:8.4-fpm-alpine` sin Node/Chromium en
runtime), el rendimiento (`DOC-NF05`), la inmutabilidad (`DOC-R05.4`) y la
política multi-tenant (`S-01`, `S-02`, `TEN-R03.3`, `RSP-R08`):

1. qué motor genera los PDF y códigos QR;
2. cómo se garantiza la inmutabilidad de cada documento emitido y su rápida
   verificación pública;
3. cómo atiende la ruta pública de verificación QR a un plantel inactivo sin
   abrir grietas en `ResolveTenant` ni en `RSP-R08`.

## Decisión

### 1. Motor de PDF y QR: `dompdf/dompdf` (^3.1) + `bacon/bacon-qr-code` (ya instalado)

- Se incorpora `dompdf/dompdf` (`^3.1`) detrás del puerto `PdfRenderer` en
  `Documents\Application\Port`, reutilizando `bacon/bacon-qr-code` (ya en
  `composer.json`) para generar el QR como SVG/PNG embebido en memoria.
- `DompdfRenderer` fija explícitamente:
  - `isRemoteEnabled = false` (impide cualquier petición HTTP/SSRF);
  - `isPhpEnabled = false` e `isJavascriptEnabled = false`;
  - `chroot` limitado al directorio local de recursos del proyecto;
  - todas las imágenes (logo, firmas leídas vía `InstitutionAssetsQuery`, QR)
    se inyectan como *data URIs* en memoria (`data:image/...;base64,...`);
  - las plantillas Blade de PDF escapan todo contenido de usuario con `{{ }}`
    (sin `{!! !!}` sobre datos del plantel, estudiante, notas o notas libres).
- En la emisión en lote (`DOC-R03`), cada boleta se genera individualmente y se
  persiste como su propio PDF inmutable; además se compone un PDF consolidado
  con saltos de página (`page-break-after: always`) para la descarga única de
  la sección.

### 2. Almacenamiento inmutable: PDF binario privado + metadatos públicos precalculados

- Cada documento emitido guarda:
  1. su **PDF binario inmutable** en el disco privado del plantel bajo
     `institutions/{institution_id}/documents/{random40}.pdf`, gestionado por
     el adaptador `LaravelDocumentStorage` (misma convención de aislamiento que
     `LaravelInstitutionFiles`, de modo que `UsedStorageQuery` de SPEC-014 lo
     contabiliza automáticamente);
  2. su **registro en PostgreSQL** (`issued_documents`, con `belongsToInstitution()`
     y `RowLevelSecurity::enable()`) con:
     - `verification_code_hash` (`char(64)`, SHA-256 del código normalizado de
       128 bits de entropía; nunca se guarda el código completo en claro en la
       tabla ni en logs, solo un prefijo/sufijo no invertible `code_hint` para
       identificarlo en el historial);
     - `pdf_path` y `pdf_sha256` (huella de integridad del PDF guardado);
     - los **campos públicos mínimos congelados al emitir**:
       `institution_name_snapshot`, `institution_dea_snapshot`,
       `student_initials`, `student_id_last4`, `kind`, `period_label`,
       `issued_at`, `expires_at`, `voided_at`.
- La **re-descarga** (`DOC-R05.5`) devuelve exactamente los bytes del PDF
  guardado en disco (respuesta binaria `Response`, nunca `StreamedResponse`,
  cumpliendo ADR 0011).
- La **verificación pública por QR** (`DOC-R07`) ejecuta únicamente una
  consulta indexada sobre `issued_documents` por `verification_code_hash` (sin
  leer disco, sin consultar `students`, `enrollments` ni `evaluation`). Por
  construcción no puede filtrar nombre completo, cédula completa, notas,
  observaciones ni motivo de anulación.

### 3. Verificación pública QR en solo lectura con plantel inactivo (enmienda acotada a `TEN-R03.3`)

- Se introduce el middleware dedicado `ResolveTenantForVerification`, aplicado
  **exclusivamente** a la ruta `GET /verificar/{code}` en `{tenant}.APP_DOMAIN`
  (con rate limiting `document-verification`: 30 req/min por plantel + IP).
- `ResolveTenantForVerification`:
  1. resuelve el subdominio mediante `ResolveTenantForVerificationHandler`
     (en `Tenancy`), que acepta planteles activos o inactivos y responde 404 si
     el subdominio es inválido o no existe;
  2. abre la transacción de `TenantActivator::forRequest()` ejecutando
     `SET TRANSACTION READ ONLY` en PostgreSQL antes de entregar el control al
     controlador (garantía a nivel de motor de BD de que **ninguna escritura**
     puede ocurrir, preservando `RSP-R08` aunque hubiera un bug);
  3. no inicia sesión, no expone el estado activo/inactivo del plantel ni el
     motivo de desactivación (`DOC-R07.5`), y siempre hace `rollback` o cierra
     la transacción de solo lectura al terminar.
- `ResolveTenant` permanece sin cambios para todas las demás rutas del plantel,
  que siguen devolviendo 404 cuando el plantel está inactivo (`TEN-R03.3`).

## Alternativas descartadas

| Tema | Alternativa descartada | Por qué se descarta |
|------|------------------------|---------------------|
| Motor PDF | Chromium / Browsershot / Gotenberg | Añade ~400 MB o un servicio sidecar nuevo a `Dockerfile.prod` y CI, mayor latencia de arranque y superficie SSRF más compleja para documentos tabulares que `dompdf` resuelve en PHP puro. |
| Motor PDF | `mpdf/mpdf` | Mayor consumo de memoria y soporte más rezagado en PHP 8.4 que `dompdf` 3.1. |
| Motor PDF | FPDF / TCPDF por coordenadas | Layout frágil y costoso de mantener cuando se añadan nuevos formatos (incluyendo los oficiales MPPE de `F0-02`). |
| Inmutabilidad | Regenerar PDF al vuelo desde snapshot JSON | Un cambio en estilos CSS, fuentes o versión de `dompdf` alteraría el archivo re-descargado respecto al entregado originalmente. |
| Código QR en BD | Guardar `verification_code` en claro en `issued_documents` | Una fuga de lectura de la tabla expondría todos los códigos activos; guardar solo `verification_code_hash` sigue el mismo estándar criptográfico que `SPEC-014` y `S-08`. |
| QR con plantel inactivo | Ruta en dominio central sin subdominio | Obligaría a consultar `issued_documents` sin RLS (`BYPASSRLS` o `SECURITY DEFINER`), violando `S-01` y `S-03`. Mantener el subdominio con `SET TRANSACTION READ ONLY` preserva RLS de punta a punta. |

## Consecuencias

- **Positivas:**
  - Cero servicios externos nuevos en Docker/CI; aprovecha `gd`, `freetype`,
    `jpeg`, `intl` y `bacon/bacon-qr-code` ya presentes.
  - Verificación QR en < 20 ms y sin riesgo de filtrar datos sensibles de
    menores.
  - Inmutabilidad real del PDF y trazabilidad de espacio usado en
    `UsedStorageQuery`.
  - Garantía a nivel de PostgreSQL (`SET TRANSACTION READ ONLY`) en la única
    excepción de lectura de `TEN-R03.3`.
- **Costes / cuidados:**
  - Los PDFs ocupan almacenamiento privado en disco/S3 (estimado ~40–90 KB por
    documento; ~3 MB por un lote de 40 boletas), lo cual queda visible en el
    uso de almacenamiento del plantel (`PLT-R04.3`).
  - Se deberá añadir una prueba de arquitectura que asegure que
    `ResolveTenantForVerification` solo se aplica a la ruta pública `GET` de
    verificación y que ningún controlador de `Documents` devuelve
    `StreamedResponse`.
  - Se actualiza `TEN-R03.3` en `specs/001-tenancy/requirements.md` al
    implementar SPEC-012 (regla `P-04`).
