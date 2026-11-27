---
spec: 005-auditoria
estado: implementada
fecha: 2026-10-11
diseño: design.md
depende-de: 000-fundaciones-hexagonales (implementada), 001-tenancy (implementada)
---

# Tareas — Bitácora de auditoría

Regla transversal: al cerrar cada tarea, `php artisan test --compact`
(incluido `--group=rls` y la suite `Architecture`) sigue en verde.

## Fase 1 — API del módulo (Application, sin Laravel)

- [x] **T-01** — `FieldChange` (rechaza campos sensibles; valores
  serializables) y `ChangeSet::diff()`.
  - Criterios: AUD-R01.2, AUD-R01.4
  - Test: `tests/Unit/Audit/Application/FieldChangeTest.php`, `ChangeSetTest.php`
- [x] **T-02** — `AuditSubject`, `AuditOrigin` (`web`, `console`, `system`) y
  `AuditEvent` (formato del tipo `modulo.objeto.accion`).
  - Criterios: AUD-R01.1, AUD-R01.3
  - Test: `tests/Unit/Audit/Application/AuditEventTest.php`, `AuditOriginTest.php`
- [x] **T-03** — Puertos `AuditTrail`, `AuditContext`, `AuditLog`;
  `ListAuditEntriesQuery`, `AuditEntryView` y `ListAuditEntriesHandler`.
  - Criterios: AUD-R05.1, AUD-R05.2
  - Test: `tests/Unit/Audit/Application/ListAuditEntriesHandlerTest.php` (log en memoria)

## Fase 2 — Datos

- [x] **T-04** — Migración `audit_entries` (`belongsToInstitution()`, índices,
  `RowLevelSecurity::enable`).
  - Criterios: AUD-R04.1
  - Test: `TenantTableTest` (automático); `tests/Feature/Audit/AuditIsolationTest.php` (grupo `rls`)
- [x] **T-05** — Migración `platform_audit_entries` con la política «sin
  plantel activo».
  - Criterios: AUD-R04.2
  - Test: `AuditIsolationTest` › invisible y no insertable con un plantel activo
- [x] **T-06** — Trigger de inmutabilidad en ambas tablas (`UPDATE`,
  `DELETE`, `TRUNCATE`), con `down()`.
  - Criterios: AUD-R03.1, AUD-R03.2, AUD-R07.1
  - Test: `tests/Feature/Audit/AuditImmutabilityTest.php` (SQL directo)

## Fase 3 — Infraestructura

- [x] **T-07** — Proxies de confianza: `TRUSTED_PROXIES` en `.env.example` y
  `trustProxies(at: …)` en `bootstrap/app.php`.
  - Criterios: AUD-R01.3 (IP real del cliente)
  - Test: `tests/Feature/TrustedProxiesTest.php` › usa `X-Forwarded-For` solo
    si la petición viene de un proxy de confianza
- [x] **T-08** — `LaravelAuditContext` (actor autenticado; origen web con IP,
  consola con operador o `system`; `useConsoleOperator()`).
  - Criterios: AUD-R01.3
  - Test: `tests/Feature/Audit/Infrastructure/LaravelAuditContextTest.php`
- [x] **T-09** — `DatabaseAuditTrail` (`record`, `recordForPlatform`, guard de
  transacción abierta, momento desde `Clock`).
  - Criterios: AUD-R01.1, AUD-R01.4, AUD-R02.1, AUD-R02.2
  - Test: `tests/Feature/Audit/Infrastructure/DatabaseAuditTrailTest.php`
    (registro completo; rollback sin registro; fallo del registro revierte el
    cambio; sin transacción falla)
- [x] **T-10** — `DatabaseAuditLog` (filtros, paginación de 50, orden,
  actor eliminado, `find`).
  - Criterios: AUD-R05.1 – AUD-R05.4
  - Test: `tests/Feature/Audit/Infrastructure/DatabaseAuditLogTest.php`
- [x] **T-11** — `AuditServiceProvider` registrado en `bootstrap/providers.php` (hecho junto con T-08).

## Fase 4 — Permiso

- [x] **T-12** — `ver-bitacora` en `RolePermissionSeeder` (Administrador,
  Director) + migración para los planteles existentes + actualizar IAM-R11.1
  en SPEC-002.
  - Criterios: AUD-R06.3
  - Test: `tests/Feature/Audit/AuditPermissionTest.php` (planteles nuevos y existentes; Coordinador no lo tiene)

## Fase 5 — Adaptadores de entrada

- [x] **T-13** — Rutas `/bitacora` y `/bitacora/{entry}`,
  `AuditLogFilterRequest`, `AuditLogController` (presentador en hora de
  Caracas); `can.viewAudit` en `HandleInertiaRequests`.
  - Criterios: AUD-R05.1 – AUD-R05.5, AUD-R06.1, AUD-R06.2
  - Test: `tests/Feature/Audit/AuditLogPageTest.php`
- [x] **T-14** — Páginas Vue `Audit/Index` y `Audit/Show`; entrada «Bitácora»
  en el menú si `can.viewAudit`.
  - Test: `AuditLogPageTest` (componente Inertia y props); revisión visual
- [x] **T-15** — `AuditLogController` a `MIGRATED_CONTROLLERS`; test de
  rendimiento en el grupo `perf` y excluir `perf` del CI.
  - Criterios: AUD-NF01, AUD-NF02
  - Test: `tests/Feature/Audit/AuditPerformanceTest.php` (grupo `perf`, manual)
  - Hallazgo: `--exclude-group` en la línea de comandos **reemplaza** la exclusión de `phpunit.xml`, así que el CI excluye `perf` explícitamente (`--exclude-group=rls,perf`).

## Verificación final

- [x] `php artisan test --compact` en verde: 386 tests (21 de `rls`).
- [x] `php artisan test --group=perf` dentro de los umbrales (5 tests).
- [x] Suite `Architecture` en verde.
- [x] `migrate:fresh --seed` (dos veces seguidas) y `migrate:rollback` del módulo funcionan. Hallazgo: `migrate:fresh` no borra funciones de PostgreSQL → `CREATE OR REPLACE FUNCTION` y el CI ejecuta `migrate:fresh` dos veces.
- [x] Pint sin cambios pendientes; `npm run build` sin errores.
- [x] `/security-review` (toca permisos y RLS, S-12): sin hallazgos de confianza alta o media (2026-10-11).
- [x] `estado: implementada` en los tres archivos.
