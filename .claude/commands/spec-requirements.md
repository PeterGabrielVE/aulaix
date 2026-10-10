---
description: Fase 1 de SDD — redacta requirements.md de una spec nueva (sin código)
argument-hint: <NNN-nombre> <descripción de la funcionalidad>
---

Redacta los requisitos de la spec **$ARGUMENTS** siguiendo el spec-driven development de AulaX.

1. Lee `specs/constitution.md`, `specs/README.md` y `specs/_templates/requirements.md`.
2. Lee las specs existentes que puedan solaparse, para no duplicar ni contradecir criterios.
3. Antes de escribir, haz las preguntas que bloqueen la redacción: actores, reglas de negocio, casos límite, qué queda fuera. No inventes reglas de negocio: si algo no está claro, pregúntalo o déjalo en «Preguntas abiertas».
4. Crea `specs/<NNN-nombre>/requirements.md` a partir de la plantilla:
   - prefijo de 3 letras nuevo y único; añádelo a `specs/README.md`;
   - historias de usuario + criterios **EARS**, uno por comportamiento observable, con ID `XXX-Rnn.m`;
   - requisitos no funcionales medibles (seguridad, privacidad, rendimiento);
   - si la funcionalidad guarda datos de una institución, añade los criterios de aislamiento (S-01, S-02).
5. Deja la matriz de trazabilidad con los criterios y la columna de tests vacía.
6. `estado: borrador`. **No escribas diseño ni código.**

Termina con un resumen: número de criterios, preguntas abiertas y riesgos que ves para el diseño.
