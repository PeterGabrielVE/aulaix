---
spec: 001-tenancy
estado: borrador
fecha: 2026-10-10
requisitos: requirements.md
depende-de: 000-fundaciones-hexagonales
---

# Diseño — Tenancy (refactor hexagonal)

## Resumen

Se crea el módulo `Tenancy`, que pasa a ser dueño de la institución, de la
resolución por subdominio y de la RLS. Lo que hoy está en `App\Models\Institution`,
`App\Support\{CurrentTenant,RowLevelSecurity}` y `App\Concerns\BelongsToInstitution`
se reparte entre las tres capas. El comportamiento no cambia: los tests de
la matriz pasan sin cambiar su lógica (Q-03).

Un punto clave: hoy `ResolveTenant` también fija el *team* de
spatie/laravel-permission. Eso es asunto de IdentityAccess, así que Tenancy
deja de conocerlo y expone un punto de extensión (`TenantActivationHook`).

## Estructura

```
src/Shared/Domain/
└── InstitutionId.php                  VO: int > 0. En Shared porque lo usan todos los módulos.

src/Tenancy/
├── Domain/
│   ├── Institution.php                agregado
│   ├── InstitutionProfile.php         VO: dea, rif, parishId, dirección, teléfono, email, logo (todos opcionales)
│   ├── InstitutionStatus.php          enum (movido desde App\Enums)
│   ├── Subdomain.php                  VO
│   ├── InvalidSubdomain.php           BusinessRuleViolation
│   └── InstitutionRepository.php      puerto
├── Application/
│   ├── CurrentTenant.php              puerto: institutionId(): ?InstitutionId, context(): ?TenantContext
│   ├── TenantContext.php              DTO: id, name, subdomain
│   ├── TenantActivator.php            puerto: activate(TenantContext), deactivate()
│   ├── TenantActivationHook.php       puerto: onActivated(TenantContext), onDeactivated()
│   ├── ResolveTenant/
│   │   ├── ResolveTenantQuery.php     subdomain: string
│   │   ├── ResolveTenantHandler.php   → TenantContext
│   │   └── InstitutionNotFound.php    NotFound (404)
│   └── SearchInstitutions/
│       ├── SearchInstitutionsQuery.php   text: string, limit: int = 10
│       ├── SearchInstitutionsHandler.php → list<InstitutionSummary>
│       ├── InstitutionSummary.php        DTO: name, subdomain
│       └── InstitutionDirectory.php      puerto de lectura (A-08)
└── Infrastructure/
    ├── Persistence/
    │   ├── InstitutionModel.php            Eloquent (movido desde App\Models\Institution)
    │   ├── InstitutionMapper.php           InstitutionModel ⇄ Institution
    │   ├── EloquentInstitutionRepository.php
    │   ├── EloquentInstitutionDirectory.php
    │   ├── RowLevelSecurity.php            movido desde App\Support
    │   └── BelongsToInstitution.php        trait movido desde App\Concerns
    ├── InMemoryCurrentTenant.php           singleton por petición
    ├── PostgresTenantActivator.php         CurrentTenant + RLS + hooks
    └── TenancyServiceProvider.php          bindings + macro Blueprint::belongsToInstitution()
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Institution` | Agregado | `id`, `name` (no vacío), `subdomain`, `status`, `profile`. `isActive()`. Sin casos de uso de escritura en la Fase 1 (las instituciones se crean por seeder); se modela completo para el futuro back-office. |
| `Subdomain` | Value Object | `Subdomain::fromString()` normaliza (trim + minúsculas) y exige `^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$` y no reservado (`RESERVED`). `Subdomain::normalize()` es estático y puro, para que el mutator de Eloquent lo reutilice. |
| `InstitutionProfile` | Value Object | Datos opcionales del plantel. La unicidad de DEA y RIF es una regla entre agregados: la garantiza el índice único y el repositorio traduce la violación. |
| `InstitutionStatus` | Enum | `Active`, `Inactive`. |
| `InstitutionId` (Shared) | Value Object | Entero positivo. |

La parroquia se referencia solo por su id (`?int`): Tenancy no importa el
dominio de Catalogs (A-06). La integridad la mantiene la FK con `nullOnDelete`
(TEN-R01.6).

## Casos de uso

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `ResolveTenantHandler` | `ResolveTenantQuery` | `TenantContext` o `InstitutionNotFound` | TEN-R03.1 – TEN-R03.4 |
| `SearchInstitutionsHandler` | `SearchInstitutionsQuery` | `list<InstitutionSummary>` | TEN-R04.3, TEN-R04.4 |

`ResolveTenantHandler`: si `Subdomain::fromString()` falla (formato inválido o
reservado), responde `InstitutionNotFound` (404), no 422. Para quien visita
la URL es simplemente una dirección que no existe.

## Puertos y adaptadores

| Puerto | Capa | Adaptador | Notas |
|--------|------|-----------|-------|
| `InstitutionRepository` | Domain | `EloquentInstitutionRepository` | `findActiveBySubdomain(Subdomain): ?Institution` |
| `InstitutionDirectory` | Application | `EloquentInstitutionDirectory` | Query service: `ILIKE`, `orderBy name`, `limit`, solo columnas `name, subdomain` |
| `CurrentTenant` | Application | `InMemoryCurrentTenant` | Singleton. Sustituye a `App\Support\CurrentTenant` |
| `TenantActivator` | Application | `PostgresTenantActivator` | Fija `CurrentTenant`, llama a `RowLevelSecurity::setInstitution()` y ejecuta los hooks |
| `TenantActivationHook` | Application | `SpatiePermissionTeamHook` (IdentityAccess) | Hasta que se migre SPEC-002 vive en `app/Providers`; luego se mueve a `IdentityAccess\Infrastructure` |

## Adaptadores de entrada (`app/`)

| Adaptador | Cambio |
|-----------|--------|
| `Http/Middleware/ResolveTenant` | Llama a `ResolveTenantHandler` y a `TenantActivator::activate()`. Conserva lo que es HTTP: `URL::defaults(['tenant' => …])` y `forgetParameter('tenant')`. |
| `Http/Controllers/InstitutionController::search` | Llama a `SearchInstitutionsHandler` y devuelve `{ data: [...] }`. |
| `Http/Middleware/HandleInertiaRequests` | Lee `CurrentTenant::context()` para compartir `institution.{name, subdomain}`. |
| `routes/web.php`, `bootstrap/app.php` | Sin cambios funcionales. |

## Datos

Sin migraciones nuevas. Se mantienen como defensa en profundidad:

- CHECK `institutions_subdomain_format` (formato y reservados), que es lo que
  verifican los tests de TEN-R02.2/R02.3 al insertar con la factory.
- Índices únicos de `dea_code` y `rif`.

Las migraciones existentes que importan `App\Support\RowLevelSecurity` pasan a
importar `AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity`. Solo
cambia el `use`; el SQL que generan es idéntico.

`database/factories/InstitutionFactory.php` apunta a `InstitutionModel`, y el
modelo declara `#[UseFactory(InstitutionFactory::class)]`.

## Patrones aplicados

| Patrón | Fuerza que resuelve |
|--------|---------------------|
| Value Object (`Subdomain`) | Una sola definición de «subdominio válido» para el dominio, el mutator y los tests. |
| Repository + Mapper | El dominio no conoce Eloquent (A-02). |
| Query service (CQRS ligero) | La búsqueda pública no necesita hidratar agregados (A-08). |
| Observer (`TenantActivationHook`) | Activar un tenant tiene efectos en otros módulos (equipo de permisos) sin que Tenancy dependa de ellos (A-06). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| Spoofing | Cabecera `Host` falsificada para entrar como otro tenant o como un dominio ajeno | `trustHosts` limitado a `APP_DOMAIN`; el tenant sale solo del subdominio de la ruta (S-02) | TEN-R03.5 |
| Tampering | Insertar filas con el `institution_id` de otra institución | Política RLS con `WITH CHECK` | TEN-R05.4 |
| Information disclosure | Consulta que olvida filtrar o SQL crudo | `FORCE ROW LEVEL SECURITY` + rol `NOBYPASSRLS` | TEN-R05.1, TEN-R05.3, TEN-NF01 |
| Information disclosure | Tabla nueva sin política | Test sobre el esquema real | TEN-NF02 |
| Information disclosure | Conexión reutilizada (worker) que conserva el tenant anterior | `TenantActivator::deactivate()` limpia la variable de sesión | TEN-R05.6 |
| Denial of service | Búsqueda pública sin límite | **Abierto: TEN-H01** | — |
| Elevation of privilege | Institución inactiva accesible por URL directa | `ResolveTenantHandler` solo resuelve activas | TEN-R03.3 |

## Errores

| Excepción | HTTP | Mensaje |
|-----------|------|---------|
| `InstitutionNotFound` | 404 | «Institución no encontrada.» |
| `InvalidSubdomain` | 422 (al crear/editar; en la resolución se convierte en `InstitutionNotFound`) | «El subdominio no es válido.» / «Ese subdominio está reservado.» |

## Estrategia de pruebas

- **Dominio:** `tests/Unit/Tenancy/SubdomainTest.php` (normalización,
  etiquetas DNS válidas e inválidas, reservados: mismos datasets que
  `SubdomainResolutionTest`); `InstitutionTest` unitario (`isActive`).
- **Aplicación:** `ResolveTenantHandlerTest` y `SearchInstitutionsHandlerTest`
  con `InMemoryInstitutionRepository`/`InMemoryInstitutionDirectory`.
- **Infraestructura:** `EloquentInstitutionRepositoryTest` (mapeo de ida y
  vuelta, `findActiveBySubdomain`) contra Postgres.
- **Feature:** todos los tests de la matriz, sin cambiar su lógica.
- **Arquitectura:** `ResolveTenant` e `InstitutionController` entran en
  `MIGRATED_CONTROLLERS` (FND-R02.6).

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| `InstitutionId` en `Shared` | En `Tenancy\Domain` | Todos los módulos lo necesitan y A-06 prohíbe importar el dominio de otro módulo. |
| Hook para el equipo de permisos | Seguir fijándolo en `ResolveTenant` | Acoplaría Tenancy a Spatie. |
| Mantener el CHECK de BD además del VO | Solo VO | Defensa en profundidad: protege también los inserts que no pasan por el dominio (seeders, SQL). |
| `RowLevelSecurity` como clase estática en Infrastructure | Puerto en Application | Hoy solo la usan migraciones y el activador; un puerto no aporta nada hasta que haya otro motor. |

## Riesgos y plan de rollback

- **Riesgo medio:** el orden de middleware (`ResolveTenant` antes de `auth`,
  ver `bootstrap/app.php`) no cambia, pero el middleware se reescribe.
  `AuthenticationTest` › «unknown subdomains are rejected» y los tests de
  redirección lo cubren.
- **Riesgo:** olvidar el hook de permisos rompe los roles por institución.
  Lo cubre `RolePermissionTest` completo.
- **Rollback:** revertir el PR; no hay migraciones de datos.
