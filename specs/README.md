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
├── adr/                       decisiones de arquitectura
├── _templates/                plantillas de requirements, design y tasks
└── NNN-nombre/
    ├── requirements.md
    ├── design.md
    └── tasks.md
```

Prefijos de IDs de requisitos por spec: `FND` (000), `TEN` (001), `IAM`
(002), `CAT` (003).

## Índice

| Spec | Módulo | Requisitos | Diseño | Tareas |
|------|--------|------------|--------|--------|
| [000 Fundaciones hexagonales](000-fundaciones-hexagonales/) | `Shared`, `Ai` | borrador | borrador | borrador |
| [001 Tenancy](001-tenancy/) | `Tenancy` | implementada¹ | borrador | borrador |
| [002 Identidad y acceso](002-identidad-acceso/) | `IdentityAccess` | implementada¹ | borrador | borrador |
| [003 Catálogos globales](003-catalogos/) | `Catalogs` | implementada¹ | borrador | borrador |

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
4. **002** — identidad y acceso: el más grande y el de más reglas.

## Decisiones

| ADR | Título | Estado |
|-----|--------|--------|
| [0001](adr/0001-arquitectura-hexagonal.md) | Arquitectura hexagonal modular en `src/` | propuesta |
| [0002](adr/0002-spec-driven-development.md) | Spec-driven development propio en el repositorio | propuesta |
| [0003](adr/0003-rls-limite-seguridad.md) | Row Level Security como límite de seguridad multi-tenant | aceptada (retroactiva) |
