---
description: Fase 3 de SDD — descompone un diseño aprobado en tasks.md
argument-hint: <NNN-nombre>
---

Descompón en tareas la spec **$ARGUMENTS**.

1. Comprueba que `specs/$ARGUMENTS/design.md` tiene `estado: aprobada`. Si no, detente y dilo.
2. Lee `specs/_templates/tasks.md`.
3. Escribe `specs/$ARGUMENTS/tasks.md`:
   - orden de dentro hacia fuera: dominio → aplicación → infraestructura → adaptadores de entrada;
   - cada tarea es pequeña, deja la suite en verde y cita los criterios que cubre y su test;
   - las migraciones de datos van en su propia tarea, con prueba de `up` y `down`;
   - agrupa en fases que puedan ir en PRs separados;
   - cierra con la verificación final de la plantilla.
4. Comprueba que todo criterio de `requirements.md` aparece en al menos una tarea.
5. `estado: borrador`.
