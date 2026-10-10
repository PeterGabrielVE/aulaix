---
description: Fase 2 de SDD — redacta design.md a partir de requisitos aprobados (sin código)
argument-hint: <NNN-nombre>
---

Diseña la spec **$ARGUMENTS**.

1. Comprueba que `specs/$ARGUMENTS/requirements.md` tiene `estado: aprobada`. Si no, detente y dilo.
2. Lee `specs/constitution.md` (secciones 2 y 3 sobre todo), `specs/_templates/design.md`, los ADR de `specs/adr/` y los diseños de los módulos que vayas a tocar.
3. Revisa el código actual del módulo en `src/` y `app/` para reutilizar puertos, value objects y adaptadores existentes.
4. Escribe `specs/$ARGUMENTS/design.md` con la plantilla:
   - cada caso de uso enumera los criterios que cubre; todo criterio queda cubierto;
   - un puerto solo cuando hay un adaptador real o un motivo para probar en aislamiento;
   - cada patrón con su fuerza concreta (C-05);
   - modelo de amenazas STRIDE completo (S-12), con un test o una regla como verificación;
   - tabla de errores de dominio → HTTP (C-06);
   - estrategia de pruebas por capa (Q-01);
   - riesgos y plan de rollback (migraciones de datos sobre todo).
5. Si hay una decisión con alternativas reales, propón un ADR nuevo en `specs/adr/`.
6. `estado: borrador`. **No escribas código.**

Termina listando las decisiones que necesitan visto bueno.
