---
spec: 006-institucion
estado: implementada
fecha: 2026-10-20
diseño: design.md
depende-de: 001-tenancy (implementada), 003-catalogos (implementada), 004-identidad-global (implementada), 005-auditoria (implementada)
---

# Tareas — Institución y configuración del plantel

Regla transversal: al cerrar cada tarea, `php artisan test --compact`
(incluidos `--group=rls` y la suite `Architecture`) sigue en verde. TDD
(Q-02): primero el test en rojo, por el motivo correcto.

## Fase 1 — Dominio (PHP puro)

- [x] **T-01** — `InstitutionType`, `InstitutionLevels` y las reglas de
  `InstitutionProfile` (correo, longitudes, DEA y RIF sin formato).
  - Criterios: INS-R01.4, R01.5, R01.8, R02.1, R02.2, R03.1, R03.2
  - Test: `tests/Unit/Tenancy/Domain/InstitutionSettingsTest.php`
- [x] **T-02** — `Institution`: `rename`, `changeProfile`, `changeType`,
  `changeLevels`, `changeLogo`; ningún método cambia el subdominio.
  - Criterios: INS-R01.2, R01.6, R01.7, R04.1, R04.5
  - Test: `tests/Unit/Tenancy/Domain/InstitutionTest.php`
- [x] **T-03** — `Signature` y `SignatureRole`.
  - Criterios: INS-R04.2
  - Test: `tests/Unit/Tenancy/Domain/SignatureTest.php`

## Fase 2 — Catálogos y permisos

- [x] **T-04** — `Catalogs\Application\EducationLevels` y
  `GeographyDirectory` (+ `EloquentGeographyDirectory`).
  - Criterios: INS-R01.5, R03.1
  - Test: `tests/Feature/Catalogs/GeographyDirectoryTest.php`, `tests/Unit/Catalogs/Application/EducationLevelsTest.php`
- [x] **T-05** — Permisos `gestionar-plantel` (Administrador) y `ver-plantel`
  (Administrador y Director); migración de re-siembra; actualizar
  IAM-R11.1 en SPEC-002.
  - Criterios: INS-R05.4
  - Test: `RoleSetTest` (permisos por rol); `tests/Feature/Institution/InstitutionPermissionsTest.php`

## Fase 3 — Datos y archivos

- [x] **T-06** — Migraciones: `type` y `education_levels` en `institutions`;
  `institution_signatures` con `belongsToInstitution` + RLS.
  - Criterios: INS-R02.1, R03.1, R04.2, S-01
  - Test: `TenantTableTest` (automático); `tests/Feature/Institution/SignatureIsolationTest.php` (grupo `rls`)
- [x] **T-07** — Repositorios: `InstitutionRepository` (`byId`, `save`,
  `isDeaCodeTaken`, `isRifTaken`) y `SignatureRepository`.
  - Criterios: INS-R01.2, R01.3
  - Test: `tests/Feature/Institution/Infrastructure/InstitutionRepositoryTest.php`
- [x] **T-08** — `InstitutionFiles` + `LaravelInstitutionFiles`: nombres
  generados, carpeta por plantel, rechazo de rutas ajenas y de imágenes que
  no se decodifican; disco configurable.
  - Criterios: INS-R04.4, R04.8, R07.2, NF02
  - Test: `tests/Feature/Institution/Infrastructure/LaravelInstitutionFilesTest.php`

## Fase 4 — Casos de uso

- [x] **T-09** — `UpdateInstitution` (datos, tipo y niveles; DEA y RIF
  únicos sin nombrar al otro plantel; plantel de `CurrentTenant`; bitácora
  de los campos cambiados).
  - Criterios: INS-R01.2 – R01.5, R01.7, R01.8, R02.*, R03.1, R03.2, R06.1, R07.1
  - Test: `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php`
- [x] **T-10** — `ChangeLogo`, `RemoveLogo`, `ChangeSignature`,
  `RemoveSignature`, con compensación de archivos y bitácora sin el contenido.
  - Criterios: INS-R04.1 – R04.5, R06.2
  - Test: `tests/Unit/Tenancy/Application/InstitutionAssetsTest.php`
- [x] **T-11** — Queries `ShowInstitution`, `InstitutionSettings` (API para
  SPEC-008) e `InstitutionAssets` (API para SPEC-012).
  - Criterios: INS-R01.1, R02.3, R03.3, R04.6, R04.7, R05.3, NF03
  - Test: `tests/Feature/Institution/Infrastructure/InstitutionQueriesTest.php` (+ caso en el grupo `perf`)

## Fase 5 — Entrada HTTP y pantalla

- [x] **T-12** — Controladores, FormRequests y rutas con permisos y
  `throttle`; servir logo y firmas con `nosniff` y `private`;
  `can.viewInstitution`, `can.manageInstitution` y la URL del logo
  compartidos. Controladores en `MIGRATED_CONTROLLERS`.
  - Criterios: INS-R01.1, R04.6 – R04.8, R05.1 – R05.3, R07.1, NF01
  - Test: `tests/Feature/Institution/InstitutionSettingsTest.php`
- [x] **T-13** — `Pages/Institution/Settings.vue` (datos, tipo, niveles,
  ubicación en cascada, logo y firmas) y la entrada del menú.
  - Criterios: INS-R01.1, NF04
  - Test: `InstitutionSettingsTest` (props de la página); `TerminologyTest`
- [x] **T-14** — Punta a punta: el nombre nuevo en el selector central;
  archivos falsos rechazados; un plantel no cambia ni lee lo de otro.
  - Criterios: INS-R01.7, R04.3, R04.4, R04.8, R07.1, R07.2
  - Test: `InstitutionSettingsTest`

## Verificación final

- [x] `php artisan test --compact` en verde (incluidos `--group=rls` y `--group=perf`).
- [x] Tests de arquitectura en verde.
- [x] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [x] `migrate:fresh --seed` dos veces y rollback de las migraciones nuevas.
- [ ] Disco compartido (S3) configurado en producción antes de habilitar las subidas.
- [x] Matriz de trazabilidad completa.
- [x] `/security-review` (subida de archivos y permisos, S-12): sin hallazgos de confianza alta o media (2026-10-23; ver «Revisión de seguridad» en `specs/roadmap.md`).
- [x] `estado` de requirements/design/tasks actualizado.
