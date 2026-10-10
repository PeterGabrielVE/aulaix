---
description: Fase 4 de SDD — implementa las tareas de una spec aprobada con TDD
argument-hint: <NNN-nombre> [T-nn]
---

Implementa la spec **$ARGUMENTS** (todas las tareas pendientes, o solo la indicada).

1. Comprueba que `requirements.md`, `design.md` y `tasks.md` tienen `estado: aprobada` o `implementando`. Si no, detente y dilo.
2. Lee la constitución, los tres documentos de la spec y las reglas de `CLAUDE.md`.
3. Pon `estado: implementando` en `tasks.md`.
4. Por cada tarea, en orden:
   1. escribe primero el test que la verifica y comprueba que falla por el motivo correcto (Q-02);
   2. implementa lo mínimo para ponerlo en verde, respetando las capas (A-01 a A-08) y las convenciones C-01 a C-07;
   3. ejecuta el test de la tarea, después la suite afectada y la suite `Architecture`;
   4. `vendor/bin/pint --dirty --format agent`;
   5. marca `[x]` en `tasks.md` y añade el test a la matriz de `requirements.md`.
5. Si el diseño no encaja con la realidad del código, **detente**: propón el cambio en `design.md` y espera aprobación (P-04). No improvises otra solución.
6. En refactors, los tests feature existentes no cambian su lógica; solo sus `use` (Q-03).
7. Al terminar: suite completa con `--group=rls`; si toca autenticación, tenancy o permisos, recomienda `/security-review` (S-12); actualiza los `estado` y el índice de `specs/README.md`.

No hagas commit ni push sin que se pida.
