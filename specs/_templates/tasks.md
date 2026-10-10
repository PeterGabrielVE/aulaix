---
spec: NNN-nombre
estado: borrador
fecha: AAAA-MM-DD
diseño: design.md
---

# Tareas — <Nombre de la funcionalidad>

Reglas:

- Una tarea = un cambio pequeño, revisable y que deja la suite en verde.
- Cada tarea cita los criterios que cubre y el test que la verifica.
- TDD (Q-02): primero el test en rojo.
- Marca `[x]` al terminar y añade el test a la matriz de `requirements.md`.

## Fase 1 — <nombre>

- [ ] **T-01** — <Qué se hace>.
  - Criterios: XXX-R01.1
  - Test: `tests/Unit/<Módulo>/…Test.php` › «…»
  - Hecho cuando: …

## Verificación final

- [ ] `php artisan test --compact` en verde (incluido `--group=rls`).
- [ ] Tests de arquitectura en verde.
- [ ] `vendor/bin/pint --dirty` sin cambios.
- [ ] Matriz de trazabilidad completa.
- [ ] `/security-review` si toca autenticación, tenancy o permisos (S-12).
- [ ] `estado` de requirements/design/tasks actualizado.
