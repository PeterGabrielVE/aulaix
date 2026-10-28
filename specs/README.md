# Specs de AulaX

Este directorio es la fuente de verdad del comportamiento del sistema. Todo
cambio funcional empieza aquí (ver [constitución](constitution.md), regla P-01).

## Flujo

```
idea ──► /spec-requirements ──► revisión ──► /spec-design ──► revisión ──► /spec-tasks ──► /spec-implement
          requirements.md          PR         design.md          PR         tasks.md        código + tests
```

1. **Requisitos** — historias de usuario y criterios EARS con ID. Qué y por
   qué; nada de cómo.
2. **Diseño** — módulos, agregados, puertos, adaptadores, datos, modelo de
   amenazas, estrategia de pruebas y decisiones (ADR si hay alternativas).
3. **Tareas** — pasos pequeños y ordenados, cada uno trazado a criterios y
   verificable con un test.
4. **Implementación** — tarea a tarea con TDD, marcando las casillas en
   `tasks.md` y completando la matriz de trazabilidad.

Los comandos de Claude Code (`.claude/commands/spec-*.md`) guían cada fase,
pero el proceso funciona igual a mano.

## Ciclo de vida

El `estado` de cada archivo va en su frontmatter:

`borrador` → `aprobada` → `implementando` → `implementada` (→ `obsoleta`)

Una spec pasa a `aprobada` cuando se mergea su PR de revisión.

## Estructura

```
specs/
├── constitution.md            reglas de proceso, arquitectura, seguridad y calidad
├── roadmap.md                 backlog → módulos → specs, dependencias y bloqueos
├── adr/                       decisiones de arquitectura
├── _templates/                plantillas de requirements, design y tasks
└── NNN-nombre/
    ├── requirements.md
    ├── design.md
    └── tasks.md
```

Prefijos de IDs de requisitos por spec: `FND` (000), `TEN` (001), `IAM`
(002), `CAT` (003), `IDG` (004), `AUD` (005), `INS` (006), `CAL` (007), `EAC` (008), `EST` (009), `MAT` (010), `EVA` (011), `NAV` (013), `PLT` (014), `RLT` (015).

## Índice

| Spec | Módulo | Requisitos | Diseño | Tareas |
|------|--------|------------|--------|--------|
| [000 Fundaciones hexagonales](000-fundaciones-hexagonales/) | `Shared`, `Ai` | implementada | implementada | implementada |
| [001 Tenancy](001-tenancy/) | `Tenancy` | implementada¹ | implementada (v2) | implementada (v2) |
| [002 Identidad y acceso](002-identidad-acceso/) | `IdentityAccess` | implementada¹ | borrador | borrador |
| [003 Catálogos globales](003-catalogos/) | `Catalogs` | implementada¹ | implementada (v2) | implementada (v2) |
| [005 Bitácora de auditoría](005-auditoria/) | `Audit` | implementada | implementada | implementada |
| [004 Identidad global](004-identidad-global/) | `IdentityAccess` | aprobada v3 (todo implementado salvo R08.12, bloqueado por D1) | implementada | implementada (PR A, B y C) |
| [006 Institución y configuración](006-institucion/) | `Tenancy` | implementada | implementada | implementada |
| [007 Año escolar y lapsos](007-anio-escolar/) | `AcademicCalendar` | implementada | implementada | implementada |
| [008 Estructura académica](008-estructura-academica/) | `AcademicStructure` | implementada | implementada | implementada |
| [009 Estudiantes y representantes](009-estudiantes/) | `Students` | implementada | implementada | implementada |
| [010 Inscripción y prosecución](010-inscripcion/) | `Enrollment` (nuevo) | implementada | implementada | implementada |
| [011 Evaluación](011-evaluacion/) | `Evaluation` (nuevo) | aprobada | aprobada | implementando (PR B) |
| [013 Layout, menú e inicio por rol](013-layout-menu/) | `Workspace` (nuevo), `app/` | implementada | implementada | implementada |
| [014 Superadministración de plataforma](014-superadministracion/) | `Platform` (nuevo) | implementada | implementada | implementada |
| [015 RLS por transacción y cierre de la revisión de seguridad](015-rls-transaccion/) | `Tenancy` (transversal), `IdentityAccess`, `Students` | implementada | implementada | implementada |

¹ Los requisitos de 001–003 documentan el comportamiento que **ya existe**
(Fase 1, F1-01 a F1-07), extraído del código y de los tests actuales. Los
diseños y tareas describen el refactor a arquitectura hexagonal, que todavía
no se ha hecho. Cada spec incluye además **hallazgos abiertos**: problemas
detectados durante el análisis que cambiarían el comportamiento y necesitan
aprobación propia.

## Orden de implementación del refactor

Patrón *strangler fig*: un módulo por PR, con toda la suite en verde en cada
paso (regla Q-03).

1. **000** — namespace `src/`, kernel compartido, tests de arquitectura,
   mapeo de excepciones. Piloto sin riesgo: el cliente de IA, que ya es un
   puerto con adaptador.
2. **003** — catálogos: solo lectura, el módulo más sencillo.
3. **001** — tenancy: del que dependen los demás.
4. **005** — bitácora de auditoría (F1-08): la identidad global debe nacer auditada.
5. **004 + 002** — identidad global (F1-07) junto con el refactor de identidad y acceso: se hacen a la vez para no refactorizar dos veces el modelo de cuentas (ver ADR 0004).

Las specs siguientes de la Fase 1 y sus bloqueos están en [roadmap.md](roadmap.md).

## Decisiones

| ADR | Título | Estado |
|-----|--------|--------|
| [0001](adr/0001-arquitectura-hexagonal.md) | Arquitectura hexagonal modular en `src/` | propuesta |
| [0002](adr/0002-spec-driven-development.md) | Spec-driven development propio en el repositorio | propuesta |
| [0003](adr/0003-rls-limite-seguridad.md) | Row Level Security como límite de seguridad multi-tenant | aceptada (retroactiva) |
| [0004](adr/0004-identidad-global.md) | Identidad global con membresías por plantel | aceptada (§6 y §7 sustituidas por 0006) |
| [0005](adr/0005-auditoria-explicita.md) | Auditoría explícita en los casos de uso, dentro de la transacción | aceptada |
| [0006](adr/0006-rls-identidad-global.md) | Aislamiento de la identidad global sin funciones privilegiadas (modifica 0004) | aceptada (enmienda C, 2026-10-16) |
| [0007](adr/0007-concurrencia-calendario.md) | Reglas entre años escolares: bloqueo por plantel y restricciones de la base de datos | aceptada |
| [0008](adr/0008-traslados-entre-planteles.md) | Traslados entre planteles: visibilidad cruzada por la RLS y copia en una sola transacción | aceptada |
| [0009](adr/0009-vistas-compuestas.md) | Vistas compuestas (layout, inicio, portal) en módulos sin tablas | aceptado |
| [0010](adr/0010-identidad-y-lecturas-de-plataforma.md) | Identidad de plataforma aparte y lecturas de un plantel por activación acotada | aceptado |
| [0011](adr/0011-plantel-activo-por-transaccion.md) | El plantel activo vive en la transacción, no en la conexión | aceptado |
| [0012](adr/0012-extension-sincrona-entre-modulos.md) | Reacciones entre módulos dentro de la misma transacción, por puntos de extensión | aceptado |
