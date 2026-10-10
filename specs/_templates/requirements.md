---
spec: NNN-nombre
prefijo: XXX
estado: borrador
fecha: AAAA-MM-DD
---

# Requisitos — <Nombre de la funcionalidad>

## Contexto

Problema que resuelve, para quién y por qué ahora. Enlaza el ítem del
backlog si existe. Nada de soluciones técnicas aquí.

## Alcance

- **Incluye:** …
- **Excluye:** … (y en qué spec se tratará, si aplica)

## Glosario

| Término | Significado |
|---------|-------------|
| … | … |

## Requisitos

### XXX-R01 — <Título corto>

**Historia:** Como <rol>, quiero <capacidad> para <beneficio>.

**Criterios de aceptación (EARS):**

- **XXX-R01.1** — Cuando <disparador>, el sistema deberá <respuesta>.
- **XXX-R01.2** — Si <condición no deseada>, entonces el sistema deberá <respuesta>.

<!--
Patrones EARS:
  Ubicuo:       El sistema deberá <respuesta>.
  Por evento:   Cuando <disparador>, el sistema deberá <respuesta>.
  Por estado:   Mientras <estado>, el sistema deberá <respuesta>.
  No deseado:   Si <condición>, entonces el sistema deberá <respuesta>.
  Opcional:     Donde <característica esté presente>, el sistema deberá <respuesta>.
Cada criterio: una sola respuesta, observable y verificable con un test.
-->

## Requisitos no funcionales

- **XXX-NF01** — Seguridad / rendimiento / accesibilidad / privacidad, con
  un umbral medible.

## Fuera de alcance y preguntas abiertas

- [ ] Pregunta que bloquea la aprobación…

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| XXX-R01.1 | `tests/Feature/…Test.php` › «nombre del test» |
