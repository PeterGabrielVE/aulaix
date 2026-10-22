---
spec: 006-institucion
prefijo: INS
estado: implementada
fecha: 2026-10-20
aprobada: 2026-10-20
backlog: F1-09, F1-10
módulo: Tenancy
depende-de: 001-tenancy, 003-catalogos (niveles), 004-identidad-global (permisos por membresía), 005-auditoria
la-usan: 008-estructura-academica (niveles), 012-documentos (logo y firmas), 014-superadministracion
---

# Requisitos — Institución y configuración del plantel

## Contexto

Cada plantel necesita mantener sus propios datos oficiales y su
configuración, sin depender del equipo técnico. El backlog lo pide en dos
ítems:

- **F1-09** — «CRUD de institución (DEA, tipo, niveles, escalas)»: el tipo
  (pública, privada o subvencionada) activará o desactivará módulos.
- **F1-10** — «Configuración del plantel (logo, firmas, datos)»: el logo y
  las firmas se reflejarán en los PDF.

Hoy los datos del plantel (SPEC-001, TEN-R01) solo se cargan desde la
plataforma. Esta spec permite que el plantel los mantenga, añade el tipo,
los niveles que ofrece, el logo y las firmas. Deja preparado lo que usarán
la estructura académica (008) y los documentos (012).

## Alcance

- **Incluye:**
  - la pantalla «Plantel», donde el Administrador edita los datos
    institucionales del propio plantel: nombre, código DEA, RIF, ubicación,
    dirección, teléfono y correo;
  - el tipo de plantel;
  - los niveles que ofrece;
  - el logo y las dos firmas (Director y Secretario/a), con el nombre y el
    cargo de quien firma;
  - el registro en la bitácora y el aislamiento por plantel.
- **Excluye:**
  - crear, activar, desactivar o eliminar planteles: es de la plataforma
    (hoy por consola; después SPEC-014 y el alta automática F4-04);
  - cambiar el subdominio: solo lo cambia la plataforma;
  - las escalas de evaluación: dependen de F0-03 y van a SPEC-011;
  - qué módulos activa cada tipo: lo dirá la spec de cada módulo afectado
    (p. ej. Pagos, F2-15);
  - generar los PDF con el logo y las firmas (SPEC-012).

## Decisiones de producto incorporadas (2026-10-20)

| Pregunta | Decisión |
|----------|----------|
| Alcance del «CRUD» | Solo editar el propio plantel. Crear, activar o desactivar planteles sigue siendo de la plataforma. |
| Quién edita | Solo el Administrador, con el permiso nuevo `gestionar-plantel`. El Director ve los datos pero no los cambia. |
| Tipo y módulos | Se guarda el tipo (pública, privada, subvencionada). Qué módulos activa lo define la spec de cada módulo. |
| Escalas | Fuera de esta spec: SPEC-011, cuando F0-03 esté validado. |
| Niveles | Varios del catálogo (Inicial, Primaria, Media General), al menos uno. Hoy se pueden quitar libremente; SPEC-008 regulará quitar un nivel con secciones. |
| Firmas | Director y Secretario(a), cada una con el nombre y el cargo de quien firma. |
| Archivos | PNG o JPEG de hasta 1 MB, en almacenamiento privado del plantel. |
| Identificadores oficiales | El plantel corrige su código DEA y su RIF, y queda en la bitácora. El subdominio solo lo cambia la plataforma. |
| Nombre del plantel | Lo cambia el propio plantel, como el resto de sus datos, y queda en la bitácora. |
| Formato del DEA y del RIF | No se valida ningún formato: basta con que no se repitan (TEN-R01.2, R01.3). |

## Glosario

| Término | Significado |
|---------|-------------|
| Plantel | La institución educativa que usa AulaX, con su subdominio. |
| Código DEA | Código del plantel asignado por el MPPE (TEN-R01). |
| RIF | Registro de información fiscal del plantel. |
| Tipo de plantel | Pública, privada o subvencionada. |
| Niveles | Los niveles educativos que ofrece el plantel, del catálogo de SPEC-003. |
| Firma | Imagen de la firma de una autoridad del plantel, con su nombre y cargo. |

## Requisitos

### INS-R01 — Datos institucionales

**Historia:** Como Administrador, quiero mantener los datos oficiales de mi
plantel, para que aparezcan correctos en el sistema y en sus documentos.

**Criterios de aceptación (EARS):**

- **INS-R01.1** — El sistema deberá mostrar a quien tenga `gestionar-plantel`
  los datos del plantel abierto: nombre, código DEA, RIF, ubicación
  (parroquia del catálogo), dirección, teléfono, correo, tipo, niveles, logo
  y firmas.
- **INS-R01.2** — Cuando el Administrador guarda los datos institucionales,
  el sistema deberá actualizarlos solo en el plantel abierto.
- **INS-R01.3** — Si el código DEA o el RIF ya los usa otro plantel, entonces
  el sistema deberá rechazar el cambio sin revelar qué plantel los usa
  (TEN-R01.2, R01.3).
- **INS-R01.4** — El sistema deberá permitir dejar vacíos el código DEA, el
  RIF, la ubicación, la dirección, el teléfono y el correo (TEN-R01.4).
- **INS-R01.5** — Si el correo no tiene un formato válido, o la parroquia no
  existe en el catálogo, entonces el sistema deberá rechazar el cambio.
- **INS-R01.6** — El sistema no deberá permitir al plantel cambiar su
  subdominio.
- **INS-R01.7** — Cuando el Administrador cambia el nombre del plantel, el
  sistema deberá mostrar el nombre nuevo en el selector central y en el
  propio plantel.
- **INS-R01.8** — El sistema no deberá exigir ningún formato al código DEA ni
  al RIF, más allá de no repetirse y de su longitud máxima (20 y 12
  caracteres).

### INS-R02 — Tipo de plantel

**Historia:** Como Administrador, quiero indicar si mi plantel es público,
privado o subvencionado, para que el sistema ofrezca lo que corresponde a
cada tipo.

**Criterios de aceptación (EARS):**

- **INS-R02.1** — El sistema deberá guardar el tipo de cada plantel como
  `pública`, `privada` o `subvencionada`.
- **INS-R02.2** — Si se indica otro valor, entonces el sistema deberá
  rechazarlo.
- **INS-R02.3** — El sistema deberá permitir a otras funcionalidades
  consultar el tipo del plantel abierto, para que cada una decida qué
  ofrece según el tipo.

### INS-R03 — Niveles que ofrece el plantel

**Historia:** Como Administrador, quiero marcar los niveles que ofrece mi
plantel, para trabajar solo con los grados que corresponden.

**Criterios de aceptación (EARS):**

- **INS-R03.1** — El sistema deberá permitir elegir uno o varios niveles del
  catálogo de SPEC-003 (Inicial, Primaria, Media General).
- **INS-R03.2** — Si no se elige ningún nivel, o se elige uno que no está en
  el catálogo, entonces el sistema deberá rechazar el cambio.
- **INS-R03.3** — El sistema deberá permitir a otras funcionalidades
  consultar los niveles del plantel abierto.

### INS-R04 — Logo y firmas

**Historia:** Como Administrador, quiero subir el logo del plantel y las
firmas del Director y del Secretario(a), para que los documentos salgan con
ellos.

**Criterios de aceptación (EARS):**

- **INS-R04.1** — Cuando el Administrador sube el logo, el sistema deberá
  guardarlo y reemplazar el anterior.
- **INS-R04.2** — Cuando el Administrador sube la firma del Director o del
  Secretario(a), con el nombre y el cargo de quien firma, el sistema deberá
  guardarla y reemplazar la anterior de ese cargo.
- **INS-R04.3** — Si el archivo no es PNG ni JPEG, o pesa más de 1 MB,
  entonces el sistema deberá rechazarlo.
- **INS-R04.4** — Si el archivo dice ser PNG o JPEG pero su contenido no lo
  es, entonces el sistema deberá rechazarlo.
- **INS-R04.5** — El sistema deberá permitir quitar el logo o una firma.
- **INS-R04.6** — El sistema deberá mostrar el logo del plantel a sus
  miembros dentro del plantel.
- **INS-R04.7** — El sistema deberá mostrar las firmas solo a quien tenga
  `gestionar-plantel`, y entregarlas a las funcionalidades que generan
  documentos del plantel (SPEC-012).
- **INS-R04.8** — El sistema no deberá servir el logo ni las firmas de un
  plantel desde una dirección pública ni a miembros de otro plantel.

### INS-R05 — Permisos

**Historia:** Como Director, quiero consultar los datos del plantel sin
poder cambiarlos, porque su mantenimiento es del Administrador.

**Criterios de aceptación (EARS):**

- **INS-R05.1** — El sistema deberá permitir los cambios de INS-R01 a R04
  solo a quien tenga el permiso `gestionar-plantel`.
- **INS-R05.2** — Si alguien sin el permiso intenta un cambio, entonces el
  sistema deberá responder 403.
- **INS-R05.3** — El sistema deberá mostrar los datos del plantel (sin las
  firmas) al Director, sin poder cambiarlos.
- **INS-R05.4** — El sistema deberá incluir `gestionar-plantel` solo en el
  rol base Administrador, también en los planteles que ya existen.

### INS-R06 — Bitácora

- **INS-R06.1** — El sistema deberá registrar en la bitácora del plantel
  (SPEC-005) cada cambio de datos institucionales, tipo y niveles, con los
  valores anterior y nuevo.
- **INS-R06.2** — El sistema deberá registrar la subida, el reemplazo o la
  retirada del logo y de cada firma, sin guardar en la bitácora el contenido
  de las imágenes.

### INS-R07 — Aislamiento por plantel

- **INS-R07.1** — El sistema deberá tomar el plantel a modificar únicamente
  del subdominio de la petición, nunca de un dato que envíe el usuario
  (S-02).
- **INS-R07.2** — El sistema deberá guardar los archivos de cada plantel
  separados de los de los demás, de forma que un plantel no pueda leer ni
  reemplazar los de otro, aunque escriba su ruta.

## Requisitos no funcionales

- **INS-NF01** (seguridad) — Toda modificación exige `gestionar-plantel` en la
  ruta y en el caso de uso (S-04).
- **INS-NF02** (seguridad) — Los archivos subidos se validan por su
  contenido, no por su nombre ni por el tipo declarado; se guardan con un
  nombre generado por el sistema, fuera del directorio público.
- **INS-NF03** (rendimiento) — Las consultas del tipo y de los niveles del
  plantel abierto (INS-R02.3, R03.3) responden en menos de 10 ms (p95).
- **INS-NF04** (usabilidad) — La pantalla funciona en 360 px de ancho (F1-11)
  y usa la terminología «plantel» (IDG-NF08).

## Cambios en otras specs

- IAM-R11.1 (SPEC-002): el Administrador recibe `gestionar-plantel`. Se
  actualiza en el PR de implementación.

## Fuera de alcance y preguntas abiertas

Ninguna pregunta abierta: todas están resueltas en [Decisiones de producto
incorporadas](#decisiones-de-producto-incorporadas-2026-10-20).

Fuera de alcance: crear, activar o desactivar planteles; cambiar el
subdominio; escalas de evaluación (SPEC-011); qué módulos activa cada tipo
(spec de cada módulo); generación de PDF (SPEC-012).

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| INS-R01.1 | `tests/Feature/Institution/InstitutionSettingsTest.php` › «the manager sees every detail of the open institution, signatures included»; `tests/Feature/Institution/Infrastructure/InstitutionQueriesTest.php` › «shows the open institution with its location, options and signatures» |
| INS-R01.2 | `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «updates the details, type and levels of the open institution only»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «saving changes the open institution only, whatever the form names» |
| INS-R01.3 | `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «rejects a DEA code or a RIF another institution uses, without naming it»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «a DEA code of another institution is refused without naming it»; `tests/Feature/Institution/Infrastructure/InstitutionRepositoryTest.php` › «tells whether another institution uses a DEA code or a RIF» |
| INS-R01.4 | `tests/Unit/Tenancy/Domain/InstitutionSettingsTest.php` › «leaves every institutional field optional»; `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «lets the details be left empty» |
| INS-R01.5 | `tests/Unit/Tenancy/Domain/InstitutionSettingsTest.php` › «refuses an email that is not one»; `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «reports every broken rule of the form at once»; `tests/Feature/Catalogs/GeographyDirectoryTest.php` › «it tells whether a parish exists and where it is» |
| INS-R01.6 | `tests/Unit/Tenancy/Domain/InstitutionTest.php` › «starts with no type nor levels, and offers no way to change its subdomain»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «saving changes the open institution only, whatever the form names» |
| INS-R01.7 | `tests/Unit/Tenancy/Domain/InstitutionTest.php` › «is renamed, trimmed, and keeps everything else»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «the new name shows in the institution and in the central selector» |
| INS-R01.8 | `tests/Unit/Tenancy/Domain/InstitutionSettingsTest.php` › «asks no format of the DEA code nor the RIF, only their length»; `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «accepts a DEA code and a RIF in any format, up to their length» |
| INS-R02.1 | `tests/Unit/Tenancy/Domain/InstitutionSettingsTest.php` › «knows the three kinds of institution»; `tests/Feature/Institution/Infrastructure/InstitutionRepositoryTest.php` › «stores the name, details, type and levels of an institution» |
| INS-R02.2 | `tests/Unit/Tenancy/Domain/InstitutionSettingsTest.php` › «refuses any other kind»; `tests/Feature/Institution/SignatureIsolationTest.php` › «an institution has a known type, or none yet»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «the form is checked at the edge too» |
| INS-R02.3 | `tests/Feature/Institution/Infrastructure/InstitutionQueriesTest.php` › «tells other modules the type and levels of the open institution, without inventing them» |
| INS-R03.1 | `tests/Unit/Catalogs/Application/EducationLevelsTest.php` › «lists the catalog levels with their names»; `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «updates the details, type and levels of the open institution only» |
| INS-R03.2 | `tests/Unit/Tenancy/Domain/InstitutionSettingsTest.php` › «offers one level at least, each once»; `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «rejects a level that is not in the catalog» |
| INS-R03.3 | `tests/Feature/Institution/Infrastructure/InstitutionQueriesTest.php` › «tells other modules the type and levels of the open institution, without inventing them» |
| INS-R04.1 | `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php` › «stores the logo and replaces the previous one, deleting its file»; `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php` › «keeps the previous logo and drops the new file when saving fails» |
| INS-R04.2 | `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php` › «keeps one signature per role, with who signs and their title»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «a signature needs who signs and their title» |
| INS-R04.3 | `tests/Unit/Tenancy/Application/ImageUploadTest.php` › «rejects other types, files over 1 MB and files that only look like an image» |
| INS-R04.4 | `tests/Unit/Tenancy/Application/ImageUploadTest.php` › «rejects other types, files over 1 MB and files that only look like an image»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «a file that only looks like an image is refused» |
| INS-R04.5 | `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php` › «removes the logo and its file»; `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php` › «removes a signature and its file» |
| INS-R04.6 | `tests/Feature/Institution/InstitutionSettingsTest.php` › «the logo is served to the members of the institution, privately and as stored»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «the logo URL is shared with the pages once there is a logo» |
| INS-R04.7 | `tests/Feature/Institution/InstitutionSettingsTest.php` › «signatures are uploaded, served and removed only by the manager»; `tests/Feature/Institution/Infrastructure/InstitutionQueriesTest.php` › «hands over the logo and the signatures of the open institution only» |
| INS-R04.8 | `tests/Feature/Institution/InstitutionSettingsTest.php` › «the images are not public nor served to another institution»; `tests/Feature/Institution/Infrastructure/LaravelInstitutionFilesTest.php` › «uses the configured disk, private by default» |
| INS-R05.1 | `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «refuses whoever may not manage the institution»; `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php` › «refuses whoever may not manage the institution, storing nothing» |
| INS-R05.2 | `tests/Feature/Institution/InstitutionSettingsTest.php` › «the Director sees the details without the signatures and cannot change them»; `tests/Feature/Institution/InstitutionSettingsTest.php` › «a member without ver-plantel cannot open the screen» |
| INS-R05.3 | `tests/Feature/Institution/InstitutionSettingsTest.php` › «the Director sees the details without the signatures and cannot change them»; `tests/Feature/Institution/Infrastructure/InstitutionQueriesTest.php` › «leaves the signatures out for whoever only views the institution» |
| INS-R05.4 | `tests/Feature/Institution/InstitutionPermissionsTest.php` › «existing institutions get gestionar-plantel for the Administrador only, and ver-plantel for the Director too»; `tests/Unit/IdentityAccess/Domain/Role/RoleSetTest.php` › «each base role carries the permissions its specs give it» |
| INS-R06.1 | `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «records only the fields that changed, inside the transaction»; `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «records nothing when nothing changed» |
| INS-R06.2 | `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php` › «records each change without the image content nor its path» |
| INS-R07.1 | `tests/Feature/Institution/InstitutionSettingsTest.php` › «saving changes the open institution only, whatever the form names»; `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` › «updates the details, type and levels of the open institution only» |
| INS-R07.2 | `tests/Feature/Institution/Infrastructure/LaravelInstitutionFilesTest.php` › «neither reads nor deletes the files of another institution, even through ..»; `tests/Feature/Institution/Infrastructure/LaravelInstitutionFilesTest.php` › «stores an image under the active institution folder with a generated name»; `tests/Feature/Institution/SignatureIsolationTest.php` › «an institution only sees its own signatures» |
