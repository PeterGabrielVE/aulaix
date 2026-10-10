---
spec: 001-tenancy
prefijo: TEN
estado: implementada
fecha: 2026-10-10
origen: F1-01, F1-02, F1-03, F1-07 (comportamiento existente)
---

# Requisitos — Tenancy: instituciones, subdominios y aislamiento

## Contexto

AulaX es multi-tenant: cada institución educativa (plantel) es un tenant con
su propio subdominio y sus datos aislados del resto, dentro de una base de
datos compartida. Esta spec documenta el comportamiento **ya implementado**
en la Fase 1, para que el refactor hexagonal (ver `design.md`) lo conserve
exactamente.

## Alcance

- **Incluye:** institución y su perfil, resolución por subdominio, dominio
  central y selector, aislamiento de datos (RLS).
- **Excluye:** usuarios, roles y autenticación (SPEC-002); catálogos (SPEC-003).

## Glosario

| Término | Significado |
|---------|-------------|
| Institución / plantel | Tenant: colegio con su subdominio, sus usuarios y sus datos. |
| `APP_DOMAIN` | Dominio base configurado (p. ej. `tusistema.com`). |
| Dominio central | `APP_DOMAIN` sin subdominio: selector de institución. |
| Tabla de tenant | Tabla con columna `institution_id` cuyos datos pertenecen a una institución. |
| Código DEA | Código del plantel asignado por el MPPE. |

## Requisitos

### TEN-R01 — Datos de la institución

**Historia:** Como operador de la plataforma, quiero registrar cada
institución con sus datos oficiales, para identificarla sin ambigüedad.

- **TEN-R01.1** — El sistema deberá almacenar el estado de una institución
  como `activa` o `inactiva`.
- **TEN-R01.2** — Si se registra un código DEA que ya usa otra institución,
  entonces el sistema deberá rechazarlo.
- **TEN-R01.3** — Si se registra un RIF que ya usa otra institución, entonces
  el sistema deberá rechazarlo.
- **TEN-R01.4** — El sistema deberá permitir crear una institución sin código
  DEA, RIF, parroquia, dirección, teléfono, correo ni logo.
- **TEN-R01.5** — El sistema deberá permitir ubicar una institución en una
  parroquia del catálogo geográfico.
- **TEN-R01.6** — Cuando se elimina una parroquia, el sistema deberá conservar
  sus instituciones sin ubicación.

### TEN-R02 — Formato del subdominio

- **TEN-R02.1** — Cuando se guarda un subdominio, el sistema deberá
  almacenarlo en minúsculas y sin espacios alrededor.
- **TEN-R02.2** — Si un subdominio no es una etiqueta DNS válida (minúsculas,
  dígitos y guiones internos, de 1 a 63 caracteres), entonces el sistema
  deberá rechazarlo.
- **TEN-R02.3** — Si un subdominio es uno de los reservados (`www`, `api`,
  `admin`, `app`, `mail`, `static`, `assets`, `cdn`), entonces el sistema
  deberá rechazarlo.

### TEN-R03 — Resolución del tenant

**Historia:** Como miembro de una institución, quiero entrar por la dirección
de mi colegio, para ver solo lo que le pertenece.

- **TEN-R03.1** — Cuando llega una petición a `{subdominio}.APP_DOMAIN` y
  existe una institución activa con ese subdominio, el sistema deberá atender
  la petición en el contexto de esa institución.
- **TEN-R03.2** — El sistema deberá resolver el subdominio sin distinguir
  mayúsculas de minúsculas.
- **TEN-R03.3** — Si la institución del subdominio está inactiva, entonces el
  sistema deberá responder 404.
- **TEN-R03.4** — Si ninguna institución tiene ese subdominio, entonces el
  sistema deberá responder 404.
- **TEN-R03.5** — Si el host tiene más de un nivel de subdominio
  (`a.b.APP_DOMAIN`) o no pertenece a `APP_DOMAIN`, entonces el sistema no
  deberá resolver ninguna institución.
- **TEN-R03.6** — Mientras se atiende una petición de una institución, las
  URLs y redirecciones que genere el sistema deberán apuntar a su mismo
  subdominio.

### TEN-R04 — Dominio central y selector

**Historia:** Como usuario que no recuerda la dirección de su colegio, quiero
buscarlo por nombre, para llegar a su pantalla de acceso.

- **TEN-R04.1** — Cuando llega una petición al dominio central, el sistema
  deberá mostrar el selector de institución, nunca una página de tenant.
- **TEN-R04.2** — Cuando llega una petición a `www.APP_DOMAIN`, el sistema
  deberá redirigir (301) a la misma ruta en el dominio central.
- **TEN-R04.3** — Cuando se busca una institución desde el selector, el
  sistema deberá devolver como máximo 10 instituciones activas cuyo nombre
  contenga el texto buscado, ordenadas por nombre, con solo su nombre y
  subdominio.
- **TEN-R04.4** — El sistema no deberá incluir instituciones inactivas en los
  resultados de la búsqueda.

### TEN-R05 — Aislamiento de datos

**Historia:** Como institución, quiero la garantía de que ninguna otra puede
ver ni modificar mis datos, aunque haya un error en el código.

- **TEN-R05.1** — Mientras no hay institución en el contexto, el sistema no
  deberá devolver ninguna fila de una tabla de tenant.
- **TEN-R05.2** — Mientras no hay institución en el contexto, el sistema
  deberá rechazar toda escritura en una tabla de tenant.
- **TEN-R05.3** — Mientras hay una institución en el contexto, el sistema
  deberá devolver solo filas de esa institución, también en consultas SQL
  directas que no pasen por el ORM.
- **TEN-R05.4** — Si se intenta insertar una fila con el `institution_id` de
  otra institución, entonces el sistema deberá rechazarla.
- **TEN-R05.5** — Cuando se inserta una fila en una tabla de tenant sin
  indicar `institution_id`, el sistema deberá asignarle la institución del
  contexto.
- **TEN-R05.6** — Cuando el contexto vuelve a una institución tras haberse
  limpiado, el sistema deberá mostrar de nuevo solo sus filas.

## Requisitos no funcionales

- **TEN-NF01** (seguridad) — El aislamiento lo impone la base de datos
  (RLS), con un rol de conexión que no puede saltarse las políticas.
- **TEN-NF02** (seguridad) — Toda tabla con columna `institution_id` tiene
  la política de aislamiento, y un test lo verifica sobre el esquema real.
  Excepción deliberada: las tablas de spatie/laravel-permission (`roles`,
  `model_has_roles`, `model_has_permissions`), que se aíslan por *team* en la
  aplicación (ver S-01 y IAM-H05).
- **TEN-NF03** (rendimiento) — `institution_id` está indexado en toda tabla
  de tenant.

## Hallazgos abiertos (requieren aprobación — cambian comportamiento)

| ID | Hallazgo | Riesgo | Propuesta |
|----|----------|--------|-----------|
| **TEN-H01** | `GET /api/institutions/search` es público y no tiene límite de peticiones. | DoS barato: cada búsqueda es un `ILIKE '%…%'` que recorre la tabla; también facilita listar todas las instituciones. | Nuevo criterio: *Si un cliente supera 30 búsquedas por minuto, entonces el sistema deberá responder 429* (S-06). |
| **TEN-H02** | El texto buscado se inserta en el `LIKE` sin escapar `%` ni `_`. | Bajo: no es una inyección SQL (va parametrizado), pero `%` devuelve cualquier institución. | Escapar comodines (S-09). |

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| TEN-R01.1 | `tests/Feature/InstitutionTest.php` › «the status is read back as an enum» |
| TEN-R01.2 | `tests/Feature/InstitutionTest.php` › «two institutions can not share a DEA code» |
| TEN-R01.3 | `tests/Feature/InstitutionTest.php` › «two institutions can not share a RIF» |
| TEN-R01.4 | `tests/Feature/InstitutionTest.php` › «the profile fields are optional» |
| TEN-R01.5 | `tests/Feature/InstitutionTest.php` › «an institution is located in a parish of the geographic catalog» |
| TEN-R01.6 | `tests/Feature/InstitutionTest.php` › «deleting a parish keeps its institutions, without a location» |
| TEN-R02.1 | `tests/Feature/SubdomainResolutionTest.php` › «a subdomain is stored lower-cased and trimmed» |
| TEN-R02.2 | `tests/Feature/SubdomainResolutionTest.php` › «a subdomain must be a valid DNS label» |
| TEN-R02.3 | `tests/Feature/SubdomainResolutionTest.php` › «reserved subdomains can not be used by an institution» |
| TEN-R03.1 | `tests/Feature/TenantResolutionTest.php` › «an active institution subdomain resolves»; `tests/Feature/SubdomainResolutionTest.php` › «colegio.tusistema.com loads the colegio institution» |
| TEN-R03.2 | `tests/Feature/SubdomainResolutionTest.php` › «the subdomain is matched regardless of letter case» |
| TEN-R03.3 | `tests/Feature/TenantResolutionTest.php` › «an inactive institution subdomain is rejected» |
| TEN-R03.4 | `tests/Feature/TenantResolutionTest.php` › «a subdomain with no matching institution is rejected» |
| TEN-R03.5 | `tests/Feature/SubdomainResolutionTest.php` › «hosts outside the base domain resolve nothing» |
| TEN-R03.6 | `tests/Feature/SubdomainResolutionTest.php` › «redirects generated by a tenant stay on its subdomain» |
| TEN-R04.1 | `tests/Feature/TenantResolutionTest.php` › «the central domain shows the institution selector, not a tenant page»; `tests/Feature/SubdomainResolutionTest.php` › «the bare domain shows the institution selector» |
| TEN-R04.2 | `tests/Feature/SubdomainResolutionTest.php` › «www redirects to the institution selector instead of being treated as a tenant» |
| TEN-R04.3 | `tests/Feature/TenantResolutionTest.php` › «the institution search endpoint only returns active institutions» |
| TEN-R04.4 | `tests/Feature/TenantResolutionTest.php` › «the institution search endpoint only returns active institutions»; `tests/Feature/InstitutionTest.php` › «the active scope excludes inactive institutions» |
| TEN-R05.1 | `tests/Feature/RowLevelSecurityTest.php` › «a query without a tenant returns no rows» |
| TEN-R05.2 | `tests/Feature/RowLevelSecurityTest.php` › «without a tenant nothing can be written» |
| TEN-R05.3 | `tests/Feature/RowLevelSecurityTest.php` › «a tenant cannot read another tenant's users even without the Eloquent scope»; `tests/Feature/RowLevelSecurityTest.php` › «raw SQL is also subject to Row Level Security»; `tests/Feature/RowLevelSecurityTest.php` › «a request on one subdomain cannot see users seeded for another»; `tests/Feature/TenantTableTest.php` › «a new tenant table only shows the current institution's rows» |
| TEN-R05.4 | `tests/Feature/RowLevelSecurityTest.php` › «inserting a row for a different institution than the session is rejected»; `tests/Feature/TenantTableTest.php` › «a new tenant table rejects rows for another institution» |
| TEN-R05.5 | `tests/Feature/TenantTableTest.php` › «a new tenant table fills institution_id from the current institution» |
| TEN-R05.6 | `tests/Feature/RowLevelSecurityTest.php` › «switching back to a tenant after clearing it shows only its rows again» |
| TEN-NF01 | `tests/Feature/RowLevelSecurityTest.php` › «the application connects with a role that can not bypass Row Level Security» |
| TEN-NF02 | `tests/Feature/TenantTableTest.php` › «every table with an institution_id column enforces Row Level Security»; `tests/Feature/TenantTableTest.php` › «every tenant isolation policy uses the same rule as RowLevelSecurity::enable()» |
| TEN-NF03 | `tests/Feature/TenantTableTest.php` › «a new tenant table indexes institution_id» |
