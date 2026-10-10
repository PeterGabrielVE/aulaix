---
spec: 003-catalogos
estado: borrador
fecha: 2026-10-10
requisitos: requirements.md
depende-de: 000-fundaciones-hexagonales
---

# Diseño — Catálogos globales (refactor hexagonal)

## Resumen

Hoy los catálogos son modelos Eloquent y seeders con la lógica de
sincronización dentro: *upsert* por código, borrar lo que ya no existe y
conservar instituciones. Esa lógica es la única regla real del módulo, así que
pasa a casos de uso que se prueban sin base de datos. Los seeders quedan como
adaptadores de entrada y las lecturas, como query services (A-08). Es el
módulo más sencillo, y por eso es el primero que se migra tras SPEC-000.

## Estructura

```
src/Catalogs/
├── Domain/
│   ├── EducationLevel.php             enum (movido desde App\Enums)
│   ├── CatalogCode.php                VO: código no vacío, en mayúsculas
│   ├── SubjectEntry.php               VO: código, nombre, nivel
│   ├── GeographicEntry.php            VO: estado → municipios → parroquias, con códigos
│   └── CatalogDiff.php                cálculo puro: qué crear, actualizar y eliminar a partir de (actual, referencia)
├── Application/
│   ├── SyncSubjects/                  SyncSubjectsHandler (fuente → diff → escritor)
│   ├── SyncGeography/                 SyncGeographyHandler
│   ├── Port/
│   │   ├── SubjectSource.php · GeographySource.php       origen de referencia
│   │   └── SubjectStore.php · GeographyStore.php         lectura del estado actual + escritura del diff
│   └── Query/
│       ├── GeographyCatalog.php       puerto de lectura: states(), municipalitiesOf(), parishesOf()
│       └── SubjectCatalog.php         puerto de lectura: byLevel()
└── Infrastructure/
    ├── Persistence/                   StateModel · MunicipalityModel · ParishModel · SubjectModel (movidos desde App\Models)
    │                                  EloquentSubjectStore · EloquentGeographyStore · Eloquent*Catalog
    ├── Source/                        JsonGeographySource (venezuela-divisions.json) · MppeSubjectSource (lista actual del SubjectSeeder)
    └── CatalogsServiceProvider.php
```

`database/seeders/{GeographicCatalogSeeder,SubjectSeeder}` se quedan donde
están (Laravel los descubre ahí) y solo invocan a los handlers.

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `CatalogDiff` | Cálculo puro | Dados el estado actual y la referencia, todos indexados por código: crea lo nuevo, actualiza nombres cambiados y elimina lo que sobra. Si la referencia ya coincide con el estado actual, devuelve un diff vacío, y eso es lo que garantiza CAT-R03.1. |
| `SubjectEntry` / `GeographicEntry` | VO | Código y nombre no vacíos; códigos únicos dentro de su nivel. |
| `EducationLevel` | Enum | `inicial`, `primaria`, `media_general`. |

CAT-R03.2 (instituciones sin ubicación) no lo implementa el dominio: lo
garantiza la FK `parish_id … nullOnDelete`. El diff solo decide eliminar las
parroquias que sobran.

## Casos de uso

| Caso de uso | Criterios |
|-------------|-----------|
| `SyncGeographyHandler` | CAT-R01.1, CAT-R01.2, CAT-R03.1, CAT-R03.2 |
| `SyncSubjectsHandler` | CAT-R02.1, CAT-R03.1, CAT-R03.3 |

Cada handler se ejecuta dentro de `TransactionManager::run()`: una
sincronización a medias no deja el catálogo inconsistente.

## Puertos y adaptadores

| Puerto | Adaptador |
|--------|-----------|
| `GeographySource` | `JsonGeographySource` |
| `SubjectSource` | `MppeSubjectSource` |
| `GeographyStore` / `SubjectStore` | `EloquentGeographyStore` / `EloquentSubjectStore` |
| `GeographyCatalog` / `SubjectCatalog` | `EloquentGeographyCatalog` / `EloquentSubjectCatalog` (sin consumidores todavía; los usará el perfil de institución) |

## Datos

Sin cambios de esquema. `InstitutionModel::parish()` (Tenancy) pasa a
apuntar a `ParishModel`. Es una relación entre adaptadores de infraestructura
de dos módulos, que se permite con una excepción explícita en el test de
arquitectura: A-06 prohíbe importar la *Infrastructure* de otro módulo, y
aquí el motivo es solo la relación Eloquent.

## Patrones aplicados

| Patrón | Fuerza que resuelve |
|--------|---------------------|
| Diff puro + Store | Las reglas de sincronización se prueban en memoria; hoy solo se prueban contra Postgres. |
| Adapter (fuentes) | Cambiar el origen (otro JSON, una API del MPPE) no toca la lógica. |
| Query service | Lecturas sin agregados (A-08). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| Tampering | Archivo JSON de origen alterado | Versionado en git y revisado en PR; `JsonGeographySource` valida la estructura y falla sin escribir | Test del adaptador con JSON inválido |
| Denial of service | Sincronización que borra en cascada datos de instituciones | Solo `nullOnDelete` hacia instituciones; ninguna FK de tenant hace `cascade` hacia catálogos | CAT-R03.2 |
| Information disclosure | — | Datos públicos; sin datos de tenants | CAT-R04.2 |

## Estrategia de pruebas

- **Dominio:** `CatalogDiffTest` (crear, renombrar, eliminar, idempotencia).
- **Aplicación:** handlers con fuente y store en memoria.
- **Infraestructura:** `JsonGeographySourceTest` (24/335/1.140, JSON inválido).
- **Feature:** `GlobalCatalogsTest` sin cambiar su lógica.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Seeders como adaptadores | Comando artisan nuevo | El despliegue ya ejecuta `db:seed --class=CatalogSeeder`. |
| Catálogos sin agregados | Agregado `State` con municipios | No hay invariantes de escritura más allá de la sincronización. |

## Riesgos y plan de rollback

- **Bajo:** solo lecturas y seeders. La idempotencia (CAT-R03.1) garantiza que
  volver a ejecutar el seeder antiguo tras un rollback no cambia nada.
