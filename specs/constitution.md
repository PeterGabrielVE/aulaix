---
versión: 1.0.0
fecha: 2026-10-10
estado: borrador
---

# Constitución de ingeniería de AulaX

Reglas no negociables para toda spec, diseño, tarea y pull request. Cada
regla tiene un ID para poder citarla en revisiones («incumple A-02»).
Cambiar una regla requiere un ADR en [`adr/`](adr/) aprobado en un PR.

---

## 1. Proceso — Spec-Driven Development

| ID | Regla |
|----|-------|
| **P-01** | Ningún cambio de comportamiento entra sin spec aprobada. Excepciones: corrección de un bug con su test de regresión, cambios de copy/estilos, actualización de dependencias sin cambio funcional. |
| **P-02** | Flujo en tres fases, cada una revisada antes de la siguiente: **requisitos** (`requirements.md`: qué y por qué) → **diseño** (`design.md`: cómo) → **tareas** (`tasks.md`: pasos verificables). Después se implementa. |
| **P-03** | Trazabilidad: cada criterio de aceptación tiene ID (`IAM-R03.2`) y al menos un test que lo verifica, listado en la matriz de trazabilidad de su `requirements.md`. Un criterio sin test no está terminado. |
| **P-04** | La spec es la fuente de verdad. Si la implementación tiene que divergir, la spec se actualiza en el mismo PR — nunca después. |
| **P-05** | Los requisitos describen comportamiento observable, sin detalles de implementación. Los criterios se escriben en formato EARS (ver [`_templates/requirements.md`](_templates/requirements.md)). |
| **P-06** | Las decisiones de arquitectura con alternativas reales se registran como ADR, no se esconden en un diseño. |

## 2. Arquitectura — hexagonal (puertos y adaptadores)

```
        ┌────────────────────── app/ (Laravel: adaptadores de entrada) ──────────────────────┐
        │  Http/Controllers · Http/Middleware · Http/Requests · Console · Providers          │
        └───────────────────────────────────┬────────────────────────────────────────────────┘
                                            │ llama a casos de uso
  src/<Módulo>/                             ▼
  ┌─────────────────────────────────────────────────────────────────────────────────────────┐
  │ Application   casos de uso (Command/Query + Handler) · puertos de salida (interfaces)  │
  │      │                                                                                  │
  │      ▼                                                                                  │
  │ Domain        agregados · entidades · value objects · servicios y eventos de dominio    │
  │      ▲                                                                                  │
  │      │ implementa puertos                                                               │
  │ Infrastructure  Eloquent · repositorios · RLS · correo · HTTP · ServiceProvider        │
  └─────────────────────────────────────────────────────────────────────────────────────────┘
```

| ID | Regla |
|----|-------|
| **A-01** | Las dependencias apuntan hacia dentro: `Infrastructure → Application → Domain`. Nunca al revés. |
| **A-02** | **Domain es PHP puro.** Prohibido: `Illuminate\*`, `Spatie\*`, `Inertia\*`, `App\*`, `Carbon\*`, helpers globales (`now()`, `config()`, `app()`, `__()`), facades. El tiempo entra por el puerto `Clock`. |
| **A-03** | **Application orquesta**, no decide reglas de negocio. Depende de Domain y de sus propios puertos. Sin `Illuminate\*`. Transacciones y publicación de eventos se hacen a través de puertos (`TransactionManager`, `EventBus`). |
| **A-04** | **Infrastructure implementa puertos.** Es el único lugar donde existen modelos Eloquent, consultas SQL, Spatie, Mail y clientes HTTP. |
| **A-05** | `app/` contiene solo adaptadores de entrada (controladores, middleware, FormRequests, comandos de consola) y el arranque. Un controlador valida la forma de la petición, invoca un caso de uso y traduce el resultado a una respuesta; no consulta Eloquent ni contiene reglas. |
| **A-06** | Los módulos se comunican solo a través de la capa Application del otro módulo (casos de uso y queries públicas) o de eventos de dominio. Prohibido importar la `Infrastructure` o el `Domain` de otro módulo, salvo `Shared`. |
| **A-07** | Cada módulo registra sus enlaces puerto → adaptador en su propio `ServiceProvider` (`src/<Módulo>/Infrastructure/<Módulo>ServiceProvider.php`). Nada de `app()`/service locator dentro de `src/`; solo inyección por constructor. |
| **A-08** | **CQRS ligero.** Las escrituras siempre pasan por el agregado y su repositorio. Las lecturas de listados y búsquedas pueden ser *query services* que devuelven DTOs de lectura directamente desde la base, sin hidratar agregados. |
| **A-09** | Estas reglas se verifican con tests de arquitectura (`arch()` de Pest) en CI. Un PR que las rompa no se mergea. |

Módulos (contextos acotados): `Shared` (kernel), `Tenancy`, `IdentityAccess`,
`Catalogs`, `Ai`. Nuevos módulos nacen con su spec.

### Convenciones de código en `src/`

| ID | Regla |
|----|-------|
| **C-01** | `declare(strict_types=1);` en todo archivo. Clases `final` por defecto; value objects y DTOs `final readonly`. |
| **C-02** | Agregados con constructor privado y constructores con nombre (`User::invite()`, `User::reconstitute()`). Las invariantes se validan en el dominio y su violación lanza una subclase de `AulaX\Shared\Domain\DomainException`, nunca `ValidationException`. |
| **C-03** | Un caso de uso = un `Command` (o `Query`) inmutable + un `Handler` con un único método público `__invoke`. Los handlers devuelven IDs o DTOs, nunca modelos Eloquent ni agregados. |
| **C-04** | Los identificadores siguen siendo los `bigint` actuales, envueltos en value objects (`UserId`, `InstitutionId`). Migrar a UUID requeriría un ADR. |
| **C-05** | Un patrón se usa cuando resuelve una fuerza concreta, y el diseño la nombra. Permitidos por defecto: Repository, Value Object, Aggregate, Domain Service, Domain Event, Adapter, Mapper, Factory (constructores con nombre). Prohibidos: repositorio genérico base, service locator, lógica de negocio en modelos Eloquent. |
| **C-06** | Las excepciones de dominio se traducen a HTTP en un único lugar (`bootstrap/app.php`), según la tabla de [SPEC-000](000-fundaciones-hexagonales/design.md). |
| **C-07** | Siguen vigentes las reglas de `CLAUDE.md` (Pint, PHPDoc, tipos de retorno, convenciones Laravel en `app/`). |

## 3. Seguridad

| ID | Regla |
|----|-------|
| **S-01** | **La RLS de PostgreSQL es el límite de seguridad multi-tenant.** Toda tabla con datos de una institución usa `belongsToInstitution()` + `RowLevelSecurity::enable()`; `TenantTableTest` lo comprueba. El filtrado por tenant en el código es una comodidad, nunca la protección. **Única excepción:** las tablas de spatie/laravel-permission (`roles`, `model_has_roles`, `model_has_permissions`, en `RLS_EXEMPT_TABLES`), que el paquete lee fuera de una petición de tenant y aísla por *team*. Añadir una tabla a la excepción requiere un ADR. |
| **S-02** | El tenant sale **únicamente** del subdominio resuelto por `ResolveTenant`. Jamás del cuerpo, la query string, cabeceras ni la sesión del usuario. |
| **S-03** | El rol de base de datos de la aplicación es `NOSUPERUSER NOBYPASSRLS` en todos los entornos, incluidos CI y producción. |
| **S-04** | Autorización en dos capas, denegando por defecto: middleware de permiso en la ruta (grueso) + política de dominio en el caso de uso (fino, p. ej. `RoleAssignmentPolicy`). |
| **S-05** | Sin enumeración de cuentas: login y recuperación responden igual exista o no la cuenta. Solo se revela «cuenta inactiva» si la contraseña era correcta. |
| **S-06** | Rate limiting en todo endpoint de autenticación, de envío de correo y público sin autenticar, con clave que incluye la institución. |
| **S-07** | Secretos solo por entorno. Parámetros con contraseñas o tokens marcados con `#[\SensitiveParameter]`. Los logs usan IDs, no correos, contraseñas ni tokens. |
| **S-08** | Los tokens (invitación, recuperación) son de un solo uso, con expiración, se guardan hasheados y pertenecen a una institución. |
| **S-09** | Validación en dos niveles: forma en el borde (FormRequest), invariantes en el dominio. Toda entrada que llega a un `LIKE` escapa `%` y `_`. |
| **S-10** | Al frontend solo viajan DTOs explícitos, nunca modelos serializados completos. |
| **S-11** | Dependencias auditadas en CI (`composer audit`, `npm audit --audit-level=high`, bloqueantes) y actualizadas con Dependabot. |
| **S-12** | Cada `design.md` incluye un modelo de amenazas STRIDE. Los PRs que tocan autenticación, tenancy o permisos pasan `/security-review` antes del merge. |
| **S-13** | Objetivo de verificación: OWASP ASVS 4.0 nivel 2. |

## 4. Calidad y pruebas

| ID | Regla |
|----|-------|
| **Q-01** | Pirámide de tests: **dominio** (unit, sin Laravel, `tests/Unit/<Módulo>`) → **aplicación** (unit, con adaptadores en memoria) → **infraestructura** (integración contra PostgreSQL real) → **feature** (HTTP de punta a punta). |
| **Q-02** | Las tareas de implementación siguen TDD: test en rojo → verde → refactor. |
| **Q-03** | En refactors de comportamiento existente, los tests feature actuales son **tests de caracterización**: deben pasar sin cambiar su lógica. Solo se permite actualizar `use`/namespaces. |
| **Q-04** | No hay umbral numérico de cobertura; manda P-03 (todo criterio tiene test). Cada invariante de dominio tiene su test unitario. |
| **Q-05** | Para mergear, el CI debe estar verde: lint, auditorías, tests, RLS, migraciones/rollback y tests de arquitectura. |

## 5. Gobierno

- Esta constitución se cambia con un ADR + PR; el PR sube la versión (semver:
  mayor = se elimina o endurece una regla, menor = regla nueva, parche = redacción).
- Las specs viven en [`specs/`](.) y siguen el ciclo de vida descrito en
  [`README.md`](README.md).
