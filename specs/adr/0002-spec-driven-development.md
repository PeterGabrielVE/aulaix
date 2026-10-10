---
estado: propuesta
fecha: 2026-10-10
---

# ADR 0002 — Spec-driven development propio en el repositorio

## Contexto

Las features de la Fase 1 (F1-01 a F1-07) se implementaron sin especificación
escrita: el comportamiento solo está documentado en el código, los tests y el
README. Con varios agentes (personas e IA) trabajando en el código, hace falta
una fuente de verdad revisable **antes** de escribir código, y trazabilidad
entre requisito y test.

## Decisión

- Specs en `specs/NNN-nombre/` con tres documentos (requisitos EARS, diseño,
  tareas), plantillas en `specs/_templates/` y reglas en
  `specs/constitution.md`.
- Comandos de Claude Code en `.claude/commands/spec-*.md` para cada fase.
- Cada fase se revisa en un PR antes de la siguiente.
- CI valida la estructura de las specs y que la matriz de trazabilidad apunte a
  tests existentes (tarea de SPEC-000).

## Consecuencias

- Más trabajo por adelantado en cada feature; menos retrabajo y menos
  ambigüedad al implementar.
- Las specs deben mantenerse al día (regla P-04), si no pierden su valor.

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| GitHub Spec Kit (`specify` CLI) | Añade una dependencia externa en Python y un flujo genérico, sin las reglas de RLS y multi-tenancy del proyecto. |
| Issues de GitHub como specs | No se versionan junto al código, ni se revisan en el mismo PR que la implementación. |
