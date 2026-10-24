---
spec: 006-institucion
estado: implementada
fecha: 2026-10-20
requisitos: requirements.md
depende-de: 001-tenancy, 003-catalogos, 004-identidad-global, 005-auditoria
---

# Diseño — Institución y configuración del plantel

## Resumen

Se amplía el módulo `Tenancy`. El agregado `Institution` gana el tipo, los
niveles y operaciones para cambiar su nombre y su perfil. Las firmas son una
tabla nueva del plantel, con RLS. El logo y las firmas se guardan como
archivos privados detrás de un puerto `InstitutionFiles`, y se sirven con un
controlador que comprueba la membresía (logo) o el permiso (firmas): nunca
desde una URL pública.

`institutions` es una tabla de plataforma **sin RLS** (el selector central
la lee sin plantel activo, SPEC-001). Por eso todos los casos de uso toman
el plantel de `CurrentTenant` y nunca de la petición (INS-R07.1, S-02).

`Catalogs` publica en su capa de aplicación dos consultas nuevas: los
niveles educativos y la geografía. `Tenancy` no importa su dominio (A-06).

## Módulo y capas

```
src/Tenancy/
├── Domain/
│   ├── Institution.php            + type, levels; rename(), changeProfile(), changeType(), changeLevels(), changeLogo()
│   ├── InstitutionProfile.php     (existente) + validación de correo y longitudes
│   ├── InstitutionType.php        enum: publica | privada | subvencionada
│   ├── InstitutionLevels.php      VO: lista no vacía y sin repetidos de códigos de nivel
│   ├── Signature.php              entidad: role, signerName, signerTitle, filePath
│   ├── SignatureRole.php          enum: director | secretary
│   ├── SignatureRepository.php    puerto
│   └── InstitutionRepository.php  + byId(), save(), isDeaCodeTaken(), isRifTaken()
├── Application/
│   ├── UpdateInstitution/         UpdateInstitutionCommand + Handler (datos, tipo, niveles)
│   ├── ChangeLogo/ · RemoveLogo/
│   ├── ChangeSignature/ · RemoveSignature/
│   ├── ShowInstitution/           query de la pantalla (A-08)
│   ├── InstitutionSettings/       query pública: tipo y niveles del plantel activo (INS-R02.3, R03.3)
│   ├── InstitutionAssets/         query pública: leer el logo y las firmas (pantalla y SPEC-012)
│   └── Port/InstitutionFiles.php  guardar, leer y borrar archivos del plantel
└── Infrastructure/
    ├── Persistence/               InstitutionModel (+ type, education_levels) · SignatureModel · repositorios
    ├── Files/LaravelInstitutionFiles.php   disco privado configurable
    └── TenancyServiceProvider.php

src/Catalogs/Application/
├── EducationLevels.php            consulta: niveles del catálogo (código y nombre)
└── Geography/GeographyDirectory.php   puerto de lectura: estados → municipios → parroquias; parishExists()
```

Fuera de `src/`:

- `app/Http/Controllers/InstitutionSettingsController.php`: muestra la
  pantalla y guarda los datos.
- `InstitutionAssetController.php`: sirve y cambia el logo y las firmas.
- `app/Http/Requests/InstitutionRequest.php` y `InstitutionImageRequest.php`.
- `resources/js/Pages/Institution/Settings.vue` y la entrada «Plantel» del
  menú, a una columna para 360 px y con la terminología «plantel»; el test
  de terminología de SPEC-004 lo vigila (INS-NF04).
- Migraciones:
  - columnas `type` y `education_levels` en `institutions`;
  - tabla `institution_signatures`;
  - permisos `gestionar-plantel` y `ver-plantel` en los planteles
    existentes.

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Institution` | Agregado (existente) | `rename(name)`: no vacío y de hasta 255 caracteres; el cambio se ve en el selector (INS-R01.7). `changeProfile(InstitutionProfile)`: el subdominio **no** forma parte del perfil ni de ningún método (INS-R01.6). `changeType(InstitutionType)` (INS-R02.1). `changeLevels(InstitutionLevels)` (INS-R03.1). `changeLogo(?path)` (INS-R04.1, R04.5). |
| `InstitutionProfile` | Value Object (existente) | DEA de hasta 20 caracteres y RIF de hasta 12, **sin formato** (INS-R01.8). Correo con formato válido (INS-R01.5). Todos los campos opcionales (INS-R01.4). |
| `InstitutionType` | Enum | `publica`, `privada`, `subvencionada`; cualquier otro valor se rechaza (INS-R02.2). Nulo hasta que el plantel lo indique: los planteles existentes no tienen tipo y no se inventa uno. |
| `InstitutionLevels` | Value Object | Uno o más códigos, sin repetir (INS-R03.2). Que existan en el catálogo lo comprueba el caso de uso con `Catalogs\Application\EducationLevels`. |
| `Signature` | Entidad (tabla propia, RLS) | Un registro por `SignatureRole` y plantel. Nombre y cargo de quien firma, obligatorios y de hasta 120 caracteres (INS-R04.2). |

La unicidad del DEA y del RIF entre planteles (INS-R01.3) es una regla de
plataforma. La comprueba el caso de uso con el repositorio, y el índice
único existente es el último respaldo.

## Casos de uso (Application)

Todos toman el plantel de `CurrentTenant` (INS-R07.1) y registran en la
bitácora dentro de su transacción (ADR 0005).

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `UpdateInstitutionHandler` | `UpdateInstitutionCommand` (nombre, DEA, RIF, parroquia, dirección, teléfono, correo, tipo, niveles) | — | R01.2 – R01.5, R01.7, R01.8, R02.1, R02.2, R03.1, R03.2, R06.1 |
| `ChangeLogoHandler` | `ImageUpload` (bytes, tipo detectado) | — | R04.1, R04.3, R04.4, R06.2, R07.2 |
| `RemoveLogoHandler` | — | — | R04.5, R06.2 |
| `ChangeSignatureHandler` | rol, nombre, cargo, `ImageUpload` | — | R04.2 – R04.4, R06.2, R07.2 |
| `RemoveSignatureHandler` | rol | — | R04.5, R06.2 |
| `ShowInstitutionHandler` (query) | `includeSignatures` | `InstitutionView` | R01.1, R05.3 |
| `InstitutionSettingsQuery` (query pública) | — | `InstitutionSettingsView` (tipo, niveles) | R02.3, R03.3, NF03 |
| `InstitutionAssetsQuery` (query pública) | `AssetKind` (logo, firma de director, firma de secretario) | `?StoredImage` (bytes y tipo) | R04.6 – R04.8 |

- **`ImageUpload`** es un value object de aplicación. El adaptador de entrada
  lo construye con el contenido del archivo y el tipo **detectado por el
  contenido** (`finfo`), nunca el declarado.
- El caso de uso acepta solo `image/png` e `image/jpeg` de hasta 1 MB
  (R04.3).
- `InstitutionFiles` además decodifica la imagen (`getimagesize`), así un
  archivo que solo imita la cabecera se rechaza (R04.4, NF02).
- **Archivos reemplazados:**
  1. se guarda el archivo nuevo;
  2. en la transacción, se actualizan la ruta y la bitácora;
  3. tras confirmar, se borra el anterior; si la transacción falla, se borra
     el nuevo.

  Así nunca queda una ruta sin archivo.

**Eventos de la bitácora:**

- `tenancy.institution.updated`: solo los campos que cambian (`ChangeSet`):
  nombre, DEA, RIF, parroquia, dirección, teléfono, correo, tipo y niveles
  (R06.1).
- `tenancy.institution.logo_changed` y `.logo_removed`.
- `tenancy.signature.changed` y `.removed`: el rol y, si cambian, el nombre y
  el cargo; **nunca** el contenido de la imagen ni su ruta (R06.2).

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `InstitutionRepository` | Domain (existente) | `EloquentInstitutionRepository` | + `byId`, `save`, `isDeaCodeTaken(code, except)`, `isRifTaken(rif, except)`. |
| `SignatureRepository` | Domain | `EloquentSignatureRepository` | RLS limita al plantel activo. |
| `InstitutionFiles` | Application | `LaravelInstitutionFiles` | `store(InstitutionId, kind, ImageUpload): path` con un nombre aleatorio bajo `institutions/{id}/`; `read(path)`; `delete(path)`. Usa el disco de `config('tenancy.files_disk')` (`local`, privado, por defecto; S3 en producción). Rechaza rutas fuera de `institutions/{id del plantel activo}/` (R07.2). |
| `EducationLevels` | Catalogs Application | — (lee el enum del dominio de Catalogs) | La API pública de los niveles para otros módulos. |
| `GeographyDirectory` | Catalogs Application | `EloquentGeographyDirectory` | Estados, municipios y parroquias para los selectores en cascada; `parishExists(id)` (R01.5). |

## Adaptadores de entrada (`app/`)

En el dominio del plantel, con `auth`, `active` y `verified`.

| Ruta | Middleware | Controlador | Caso de uso |
|------|------------|-------------|-------------|
| `GET /plantel` | `permission:ver-plantel` | `InstitutionSettingsController@show` | `ShowInstitution` (con firmas solo si tiene `gestionar-plantel`) |
| `PUT /plantel` | `permission:gestionar-plantel`, `throttle:30,1` | `@update` (`InstitutionRequest`) | `UpdateInstitution` |
| `POST /plantel/logo` · `DELETE /plantel/logo` | `permission:gestionar-plantel`, `throttle:10,1` | `InstitutionAssetController` | `ChangeLogo` / `RemoveLogo` |
| `POST /plantel/firmas/{role}` · `DELETE` | `permission:gestionar-plantel`, `throttle:10,1` | `InstitutionAssetController` | `ChangeSignature` / `RemoveSignature` |
| `GET /plantel/logo` | (cualquier miembro) | `@logo` | `InstitutionAssets` (logo) |
| `GET /plantel/firmas/{role}` | `permission:gestionar-plantel` | `@signature` | `InstitutionAssets` (firma) |
| `GET /api/geografia/...` | (cualquier miembro) | `GeographyController` | `GeographyDirectory` |

- **Servir imágenes:** las respuestas llevan el tipo de contenido guardado,
  `Cache-Control: private`, `X-Content-Type-Options: nosniff` y
  `Content-Disposition: inline`. Nunca se usan las URL del disco ni el
  servicio de archivos de Laravel (`serve`).
- **Validación en el borde:** `InstitutionRequest` comprueba la forma
  (longitudes, correo, tipo dentro del enum, niveles como lista).
  `InstitutionImageRequest` comprueba `file`, `max:1024` y
  `mimetypes:image/png,image/jpeg`, que en Laravel se basa en el contenido.
  El caso de uso lo comprueba otra vez (S-09).
- **Permisos en la interfaz:** `HandleInertiaRequests` comparte
  `can.viewInstitution` y `can.manageInstitution`, y la URL del logo del
  plantel para la cabecera (R04.6).

## Datos

```
institutions  (tabla de plataforma, sin RLS: SPEC-001)
  + type varchar(16) NULL  CHECK (type IN ('publica', 'privada', 'subvencionada'))
  + education_levels jsonb NOT NULL DEFAULT '[]'
  logo_path (existente): ruta en el disco privado

institution_signatures  (belongsToInstitution + RowLevelSecurity::enable)   S-01
  id, institution_id, role varchar(16) CHECK (role IN ('director', 'secretary')),
  signer_name varchar(120), signer_title varchar(120), file_path varchar, timestamps
  UNIQUE (institution_id, role)
```

- Los planteles existentes quedan con `type = NULL` y `education_levels =
  []`. La pantalla pide completarlos, y las consultas públicas devuelven
  nulo o vacío: no se inventan valores.
- **Ubicación de los archivos:** `institutions/{institution_id}/logo-{aleatorio}.{png|jpg}`
  y `institutions/{institution_id}/firma-{rol}-{aleatorio}.{png|jpg}`. El
  disco se configura con `INSTITUTION_FILES_DISK`; por defecto `local`
  (privado, `storage/app/private`).
- **Permisos:**
  - `gestionar-plantel` solo para el Administrador (INS-R05.4);
  - `ver-plantel` para Administrador y Director (INS-R05.3);
  - migración de re-siembra, como la de SPEC-007.

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Agregado (`Institution`) | El nombre, el perfil, el tipo, los niveles y el logo cambian juntos en un solo guardado, con sus reglas en un solo lugar. El subdominio queda fuera de sus métodos. |
| Value Object (`InstitutionLevels`, `ImageUpload`) | «Al menos un nivel, sin repetir» y «solo PNG o JPEG reales de hasta 1 MB» aparecen en el dominio, el caso de uso y el adaptador: un solo tipo los garantiza. |
| Puerto + adaptador (`InstitutionFiles`) | El disco cambia por entorno (local o S3) y los tests lo sustituyen por uno en memoria. La regla «solo bajo la carpeta del plantel» vive en un solo lugar. |
| Query service (`InstitutionSettings`, `InstitutionAssets`) | API estable para SPEC-008 y SPEC-012, sin exponer el agregado (A-06, A-08). |
| Compensación (borrar el archivo nuevo si la transacción falla) | El disco no es transaccional: así no quedan rutas sin archivo ni archivos huérfanos. |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Un Director o un Docente cambia los datos o las firmas | `permission:gestionar-plantel` en la ruta y el permiso en el caso de uso | Tests feature de 403 (R05.2) |
| **T**ampering | Modificar otro plantel enviando su id | No hay id en la petición: el plantel sale de `CurrentTenant` (S-02); `institutions` se actualiza solo por ese id | Test feature: la petición en el subdominio A no cambia B (R07.1) |
| **T**ampering | Subir un archivo malicioso (polígloto, script, SVG, ejecutable renombrado) | Tipo detectado por el contenido, decodificación de la imagen, solo PNG o JPEG, nombre generado, sin SVG, `nosniff` al servir | Tests de R04.3, R04.4 con archivos falsos |
| **T**ampering | Recorrer rutas (`../`) para leer o reemplazar archivos de otro plantel | Rutas generadas por el sistema; el adaptador rechaza cualquier ruta fuera de `institutions/{id activo}/` | Test de `LaravelInstitutionFiles` (R07.2) |
| **R**epudiation | «Yo no cambié el RIF» | Bitácora de cada cambio, con valores anterior y nuevo | Tests de R06.1, R06.2 |
| **I**nformation disclosure | Ver las firmas desde fuera o desde otro plantel | Disco privado; las firmas se sirven solo con `gestionar-plantel`, y la RLS de `institution_signatures` limita al plantel activo | Tests de R04.7, R04.8 |
| **I**nformation disclosure | Averiguar qué plantel usa un DEA o un RIF | Mensaje genérico, sin nombrar al plantel que lo tiene (R01.3) | Test feature |
| **D**enial of service | Subidas grandes o repetidas | 1 MB como máximo, `throttle:10,1` en las subidas | Regla en las rutas; test de R04.3 |
| **E**levation of privilege | El Administrador de un plantel se da permisos en otro | Los permisos son por *team* (plantel); `gestionar-plantel` solo actúa sobre el plantel activo | Tests de R05.1, R07.1 |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario (ejemplos) |
|----------------------|------|-------------------------------|
| `BusinessRuleViolation` en el campo | 422 | «El nombre del plantel es obligatorio.» · «Elige al menos un nivel.» · «Ese código DEA ya está registrado.» · «Ese RIF ya está registrado.» |
| `InvalidImage` (`BusinessRuleViolation`) | 422 en `file` | «Sube una imagen PNG o JPEG de hasta 1 MB.» |
| Falta el permiso (middleware) | 403 | — |
| Logo o firma inexistentes al servirlos | 404 | — |

## Estrategia de pruebas

- **Dominio (unit):** `Institution` (renombrar, perfil, tipo, niveles, logo;
  sin método para el subdominio), `InstitutionLevels`, `InstitutionType`,
  `InstitutionProfile` (correo, longitudes, sin formato de DEA ni RIF),
  `Signature`.
- **Aplicación (unit, adaptadores en memoria):**
  - cada handler toma el plantel de `CurrentTenant`;
  - registra solo los campos cambiados;
  - en una subida fallida borra el archivo nuevo, y en una exitosa borra el
    anterior;
  - DEA y RIF repetidos se rechazan sin nombrar al otro plantel.
- **Infraestructura (Postgres y disco temporal):**
  - repositorios y `TenantTableTest` para `institution_signatures` (RLS, grupo
    `rls`);
  - `LaravelInstitutionFiles`: nombres generados, rechazo de rutas ajenas y
    de imágenes que no se decodifican;
  - `GeographyDirectory`;
  - consultas públicas por debajo de 10 ms (grupo `perf`).
- **Feature (HTTP):** la pantalla del Administrador y del Director (sin
  firmas), 403 para los demás, guardar los datos, subir, reemplazar y quitar
  el logo y las firmas, servirlas solo a quien corresponde, archivos falsos,
  el selector con el nombre nuevo y la bitácora.
- **Arquitectura:** controladores en `MIGRATED_CONTROLLERS`; `Tenancy` usa
  `Catalogs` solo por su capa `Application`, además del enlace ya
  permitido a `ParishModel`.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Permiso nuevo `ver-plantel` (Administrador y Director) para la pantalla en lectura | Abrir `GET /plantel` a todos los miembros | El requisito solo da la vista al Director (INS-R05.3); un permiso explícito deja ampliarlo sin tocar código. |
| Firmas en tabla propia con RLS | Columnas en `institutions` | `institutions` no tiene RLS, y las firmas son datos del plantel que no deben poder leerse sin plantel activo. |
| Niveles como `jsonb` en `institutions` | Tabla `institution_levels` | Tres valores posibles y ninguna consulta por nivel; SPEC-008 podrá normalizarlo si lo necesita. |
| Archivos servidos por un controlador propio | URL firmadas temporales del disco | El controlador comprueba membresía y permiso en cada petición; una URL firmada se puede reenviar a terceros mientras dura. |
| Tipo nulo hasta que el plantel lo indique | Un tipo por defecto | No hay un valor correcto que suponer: inventarlo daría a otros módulos un dato falso. |
| Consultas públicas nuevas en `Catalogs\Application` | Que `Tenancy` lea el enum del dominio de `Catalogs` | A-06 prohíbe importar el dominio de otro módulo. |

## Riesgos y plan de rollback

- **Migración de columnas.** Solo añade columnas nulas o con valor por
  defecto; el `down()` las quita. No toca datos existentes.
- **Archivos en producción.** Con varios servidores el disco `local` no se
  comparte. Producción debe usar S3 u otro disco compartido
  (`INSTITUTION_FILES_DISK`) antes de habilitar las subidas. Queda en la
  lista de comprobación del despliegue.
- **Logo existente.** `logo_path` ya existe pero nadie lo rellena hoy. Si
  algún plantel lo tuviera con una ruta pública antigua, la migración la deja
  como está y la pantalla ofrece subirlo de nuevo.
- **Permisos.** La re-siembra solo añade los dos permisos nuevos; el
  `down()` los borra.

## Notas de implementación

Ajustes hechos al implementar, sin cambiar ningún criterio:

- **Permiso en el caso de uso (INS-NF01, S-04).** Se añadió el puerto
  `Tenancy\Application\Port\InstitutionPermissions::canManage()`, con el
  adaptador `GateInstitutionPermissions` (pregunta al *gate* de Laravel por
  `gestionar-plantel`). Así `Tenancy` no importa el dominio de
  `IdentityAccess` (A-06). Todos los casos de uso de escritura lo comprueban
  con `OpenInstitution::forManagement` y lanzan `AuthorizationDenied` (403).
- **`ImageUpload` reúne todas las reglas del archivo.** Detecta el tipo por
  el contenido (`finfo`), limita a 1 MB y además decodifica la imagen
  (`getimagesizefromstring`) y exige que el tipo coincida. Así
  `InstitutionFiles` solo puede guardar imágenes válidas y no repite la
  comprobación.
- **`InstitutionFiles` toma el plantel de `CurrentTenant`.** `store(kind,
  image)` no recibe el id: la carpeta siempre es la del plantel activo.
  `read` y `delete` solo aceptan rutas con la forma exacta que genera
  `store` (`institutions/{id}/{tipo}-{40 caracteres}.{png|jpg}`); cualquier
  otra, incluida una con `..`, se ignora (INS-R07.2).
- **Nombres de archivo en inglés.** `logo-…`, `director-signature-…` y
  `secretary-signature-…` (enum `AssetKind`), en lugar de `firma-{rol}-…`.
- **Disco propio.** Por defecto `INSTITUTION_FILES_DISK=institution_files`,
  un disco local privado en `storage/app/institution-files` **sin** `serve`:
  el disco `local` de Laravel tiene `serve: true` y podría servir archivos
  con URL firmadas, lo que INS-R04.8 prohíbe.
- **Todos los errores del formulario a la vez.** `UpdateInstitutionHandler`
  junta las reglas del dominio, la unicidad del DEA y del RIF y la parroquia
  en un solo `BusinessRuleViolations`. Para eso `BusinessRuleViolations`
  expone `violations()`. Si nada cambia, no se guarda ni se registra nada.
- **URL del logo con versión.** `TenantContext` lleva `logoVersion` (un
  resumen de la ruta), y la URL compartida es `/plantel/logo?v=…`: cambia con
  cada logo, así el navegador no muestra uno viejo.
- **La prop de la página se llama `details`.** `institution` ya es una prop
  compartida (nombre y logo para la cabecera); usar el mismo nombre la habría
  pisado.
- **Rutas de la geografía.** `GET /api/geografia/estados/{state}/municipios`
  y `/municipios/{municipality}/parroquias`; los estados llegan ya en la
  pantalla.
