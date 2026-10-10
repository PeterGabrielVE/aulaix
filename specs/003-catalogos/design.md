---
spec: 003-catalogos
estado: implementada
versión: 2
fecha: 2026-10-10
requisitos: requirements.md
depende-de: 000-fundaciones-hexagonales
---

# Diseño — Catálogos globales (refactor hexagonal)

## Resumen

Hoy los catálogos son modelos Eloquent y dos seeders con las reglas de
sincronización dentro. Esas reglas son la única lógica de negocio del módulo
y **no son iguales para los dos catálogos**:

| Catálogo | Política actual | Criterios |
|----------|-----------------|-----------|
| Materias | **Autoritativa**: el catálogo de referencia es la verdad. Se insertan o actualizan por código y se eliminan las que ya no están. | CAT-R03.1, CAT-R03.3 |
| Geografía | **Aditiva**: se insertan o actualizan por código (incluido el padre), y **nunca** se elimina nada, salvo el catálogo provisional de la primera versión (estados `VE-NN`). | CAT-R03.1, CAT-R03.2 |

El refactor hace explícitas esas dos políticas en el dominio y valida el
catálogo de referencia entero **antes** de escribir: hoy un JSON con un código
repetido o un municipio fuera de su estado se escribiría a medias. Los
seeders quedan como adaptadores de entrada.

> **v2.** La v1 proponía un `CatalogDiff` que también eliminaba geografía
> sobrante. Eso sería un comportamiento nuevo, no un refactor: borraría
> parroquias y dejaría instituciones sin ubicación. Además, el diff
> obligaba a cargar el estado actual cuando el *upsert* por código ya es
> idempotente. Los *query services* de lectura se posponen a SPEC-006, su
> primer consumidor (C-05).

## Estructura

```
src/Catalogs/
├── Domain/
│   ├── EducationLevel.php           enum (movido desde App\Enums)
│   ├── CatalogCode.php              VO: no vacío, sin espacios, ≤ 255
│   ├── InvalidCatalog.php           BusinessRuleViolation
│   ├── Subject/
│   │   ├── SubjectEntry.php         VO: código, nombre, nivel
│   │   └── SubjectCatalog.php       colección validada: códigos únicos, ≥ 1 materia por nivel
│   └── Geography/
│       ├── StateEntry.php · MunicipalityEntry.php · ParishEntry.php     VOs con hijos
│       ├── GeographicCatalog.php    árbol validado (ver invariantes)
│       └── LegacyPlaceholder.php    regla: qué códigos de estado son del catálogo provisional
├── Application/
│   ├── Port/
│   │   ├── SubjectSource.php · GeographySource.php      leen el catálogo de referencia
│   │   └── SubjectStore.php · GeographyStore.php        escriben según la política
│   ├── SyncSubjectsHandler.php
│   └── SyncGeographyHandler.php
└── Infrastructure/
    ├── Persistence/                 StateModel · MunicipalityModel · ParishModel · SubjectModel (movidos desde App\Models)
    │                                EloquentSubjectStore · EloquentGeographyStore
    ├── Source/                      JsonGeographySource (venezuela-divisions.json) · MppeSubjectSource (lista actual de SubjectSeeder)
    └── CatalogsServiceProvider.php
```

`database/seeders/{GeographicCatalogSeeder,SubjectSeeder}` se quedan donde
están (Laravel los descubre ahí) y solo invocan a los handlers. Conservan su
PHPDoc, que documenta el origen de los datos.

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `CatalogCode` | VO | No vacío, ≤ 255 caracteres (columna `varchar` actual), sin espacios. |
| `SubjectCatalog` | Colección validada | Códigos únicos; nombres no vacíos; al menos una materia por cada `EducationLevel` (CAT-R02.1). |
| `GeographicCatalog` | Árbol validado | Códigos únicos en cada nivel; todo estado tiene ≥ 1 municipio y todo municipio ≥ 1 parroquia (CAT-R01.1); el código de un hijo empieza por el de su padre (`VE-A-01` ∈ `VE-A`, `VE-A-01-01` ∈ `VE-A-01`). |
| `LegacyPlaceholder` | Regla pura | `isPlaceholderState(code)` ⇔ `^VE-[0-9]{2}$`. Es la regla que hoy vive en `removePlaceholderCatalog()`. |
| `EducationLevel` | Enum | `inicial`, `primaria`, `media_general`. |

Si un catálogo de referencia viola una invariante, se lanza `InvalidCatalog`
antes de cualquier escritura.

CAT-R03.2 (instituciones conservadas sin ubicación) no es lógica del dominio:
la garantiza la FK `institutions.parish_id … nullOnDelete`, y las parroquias
del catálogo provisional se borran en cascada desde su estado.

## Casos de uso

| Caso de uso | Flujo | Criterios |
|-------------|-------|-----------|
| `SyncGeographyHandler` | `GeographySource::load()` → `GeographicCatalog` (valida) → `TransactionManager::run(fn => store->removeLegacyPlaceholders(); store->upsert($catalog))` | CAT-R01.1, CAT-R01.2, CAT-R03.1, CAT-R03.2 |
| `SyncSubjectsHandler` | `SubjectSource::load()` → `SubjectCatalog` (valida) → `TransactionManager::run(fn => store->replaceWith($catalog))` | CAT-R02.1, CAT-R03.1, CAT-R03.3 |

Hoy los seeders no usan transacción: una sincronización interrumpida deja el
catálogo a medias. Con `TransactionManager` es atómica. Es una mejora de
robustez que no cambia ningún comportamiento observable de los requisitos.

## Puertos y adaptadores

| Puerto | Adaptador | Notas |
|--------|-----------|-------|
| `GeographySource` | `JsonGeographySource` | Lee `database/seeders/data/venezuela-divisions.json` con `JSON_THROW_ON_ERROR`. |
| `SubjectSource` | `MppeSubjectSource` | La lista actual de `SubjectSeeder::SUBJECTS`, sin cambios. |
| `GeographyStore` | `EloquentGeographyStore` | `removeLegacyPlaceholders()` usa `LegacyPlaceholder`; `upsert()` mantiene los `upsert` por código actuales (estados → municipios con `state_id` → parroquias con `municipality_id`). Nunca borra. |
| `SubjectStore` | `EloquentSubjectStore` | `replaceWith()` = `upsert` por código + borrar los códigos ausentes. |

## Datos

Sin cambios de esquema ni de datos.

- Los modelos se mueven con `git mv` y conservan sus relaciones
  (`State::municipalities()`, `Municipality::parishes()`…), que usan
  `GlobalCatalogsTest` e `InstitutionTest`.
- Mientras no se implemente SPEC-001, `App\Models\Institution::parish()` solo
  cambia su `use` hacia `ParishModel`: `app/` puede usar la infraestructura de
  un módulo. Cuando Tenancy se migre, esa relación será una excepción
  explícita del test de arquitectura (SPEC-001).
- Los modelos no tienen factories, así que no hace falta `#[UseFactory]`.

## Patrones aplicados

| Patrón | Fuerza que resuelve |
|--------|---------------------|
| Value Objects con invariantes (`GeographicCatalog`, `SubjectCatalog`) | Un catálogo de referencia incoherente se rechaza entero, antes de escribir. |
| Políticas explícitas por catálogo (Store `upsert` vs `replaceWith`) | Una diferencia de negocio deliberada (aditiva vs autoritativa) deja de estar implícita en dos seeders. |
| Adapter (fuentes) | Cambiar el origen (otro JSON, una API del MPPE) no toca la validación ni la política. |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| Tampering | Catálogo de referencia alterado o corrupto | Versionado en git y revisado en PR; validación completa del árbol antes de escribir; escritura atómica | `GeographicCatalogTest`, test de handler con fuente inválida |
| Denial of service | Sincronización que borra ubicaciones de instituciones | Política aditiva para geografía: solo se borra el catálogo provisional | CAT-R03.2, test del store |
| Information disclosure | — | Datos públicos; sin datos de tenants | CAT-R04.2 |

## Errores

| Excepción | Cuándo | Efecto |
|-----------|--------|--------|
| `InvalidCatalog` | Catálogo de referencia que viola una invariante | El seeder falla sin escribir nada; el despliegue lo muestra en el log. |
| `JsonException` | JSON mal formado | Ídem (comportamiento actual). |

## Estrategia de pruebas

- **Dominio (unit):** `CatalogCodeTest`; `SubjectCatalogTest` (códigos
  duplicados, nivel sin materias); `GeographicCatalogTest` (código duplicado,
  estado sin municipios, hijo con prefijo de otro padre); `LegacyPlaceholderTest`.
- **Aplicación (unit):** handlers con fuente y store en memoria. Una fuente
  inválida no llama al store; la geografía nunca pide borrar fuera del catálogo
  provisional.
- **Infraestructura (feature):** `JsonGeographySourceTest` (el archivo real es
  válido: 24 / 335 / 1.140).
- **Feature:** `GlobalCatalogsTest` e `InstitutionTest` sin cambiar su
  lógica (Q-03).

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Dos políticas explícitas | Un diff genérico para ambos catálogos | El diff aplicaría a la geografía la política autoritativa, un comportamiento nuevo con riesgo de perder ubicaciones de instituciones. |
| Validar el árbol completo antes de escribir | Validar al vuelo durante el upsert | Una escritura a medias es peor que un fallo limpio. |
| Seeders como adaptadores | Comando artisan nuevo | El despliegue ya ejecuta `db:seed --class=CatalogSeeder`. |
| Sin *query services* por ahora | Crearlos ya | No tienen consumidor; se añaden con SPEC-006 (C-05). |

## Riesgos y plan de rollback

- **Bajo:** sin cambios de esquema ni de datos. Si el árbol real del JSON
  incumpliera alguna invariante nueva (p. ej. el prefijo de los códigos), el
  test de `JsonGeographySource` lo detecta antes del merge; en ese caso se
  revisa la invariante, no el archivo.
- **Rollback:** revertir el PR. La idempotencia (CAT-R03.1) garantiza que el
  seeder antiguo, ejecutado de nuevo, no cambia nada.
