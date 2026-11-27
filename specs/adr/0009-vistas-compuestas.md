---
adr: 0009
título: Vistas compuestas en un módulo sin tablas (Workspace)
estado: aceptado
fecha: 2026-10-23
spec: 013-layout-menu
---

# ADR 0009 — Vistas compuestas en un módulo sin tablas

## Contexto

El roadmap dice que el portal del representante (F2-07), los dashboards por
rol (F1-12) y las apps móviles son «vistas, no módulos»: componen consultas
de varios módulos y no tienen dominio propio.

SPEC-013 muestra que estas vistas sí tienen reglas de producto:
- quién ve cada entrada del menú y cada bloque del inicio;
- qué se añade cuando una persona tiene varios roles;
- qué no debe viajar al navegador sin permiso (NAV-R10.2).

Esas reglas deben probarse por rol, sin HTTP, y respetar A-06.

## Decisión

Las vistas compuestas viven en módulos de `src/` **sin tablas ni
escrituras**:

- **Domain:** solo las reglas de composición (qué se muestra, en qué orden
  y a quién), en PHP puro.
- **Application:** consultas que piden los datos a través de puertos, uno
  por módulo fuente.
- **Infrastructure:** un adaptador por puerto, que llama a la capa
  Application del módulo dueño (A-06), y el ServiceProvider.

El primero es `Workspace` (layout, menú e inicio). El portal del
representante y las vistas para móvil seguirán el mismo patrón.

Un módulo de vista no crea tablas, no publica eventos de dominio y no
escribe. Si una vista necesita guardar algo (p. ej. preferencias del
inicio), esa parte pasa a un módulo con datos y su propia spec.

## Alternativas

| Alternativa | Por qué no |
|-------------|------------|
| Componer en los controladores de `app/` | Las reglas quedarían en adaptadores de entrada (contra A-05), sin tests de arquitectura y probadas solo por HTTP. |
| Un servicio que consulte las tablas de todos los módulos | Rompe A-06 y la propiedad de los datos; cada cambio de esquema rompería la vista. |
| Que cada módulo aporte sus bloques del inicio (registro por *tags*) | Reparte la regla de visibilidad entre módulos y complica el orden y la deduplicación entre roles (NAV-R04.6 – R04.8). Puede revisarse si los módulos llegan a ser muchos. |

## Consecuencias

- Hay módulos sin `Infrastructure/Persistence`; los tests de arquitectura
  los tratan igual que a los demás.
- Cada dato nuevo del inicio exige una consulta pública en su módulo
  dueño, más un puerto y un adaptador en `Workspace`.
- La frase del roadmap pasa a ser «vistas como módulos sin tablas».
