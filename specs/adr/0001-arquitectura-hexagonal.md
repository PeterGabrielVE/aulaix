---
estado: propuesta
fecha: 2026-10-10
---

# ADR 0001 — Arquitectura hexagonal modular en `src/`

## Contexto

AulaX es hoy un Laravel MVC clásico: las reglas de negocio viven repartidas
entre FormRequests (las reglas de asignación de roles están en closures de
`UserRequest`), modelos Eloquent (`User::homeRoute()`) y controladores. La
Fase 1 es mayormente infraestructura (tenancy, autenticación), pero las fases
siguientes (matrícula, evaluación, calificaciones) concentran reglas de
negocio complejas que deben poder probarse sin base de datos ni HTTP, y
evolucionar sin arrastrar el framework.

## Decisión

1. Todo el código de negocio se organiza en **módulos** bajo `src/`
   (namespace `AulaX\`), cada uno con capas `Domain`, `Application` e
   `Infrastructure`. `app/` queda para los adaptadores de entrada de Laravel
   y el arranque.
2. **Entidades de dominio separadas de los modelos Eloquent.** Los modelos
   Eloquent pasan a ser detalles de persistencia en `Infrastructure`, y un
   *mapper* traduce entre ambos.
3. Se aplica a **todo el código existente** mediante refactor incremental
   (*strangler fig*), un módulo por PR, protegido por la suite de tests
   actual como tests de caracterización.
4. Las reglas de dependencia se verifican con tests de arquitectura de Pest.

## Consecuencias

**Positivas**
- Las reglas de negocio se prueban en milisegundos, sin Laravel ni Postgres.
- Cambiar un detalle técnico (paquete de permisos, proveedor de correo) solo
  toca un adaptador.
- Los límites entre módulos son explícitos y verificables en CI.

**Negativas / costes asumidos**
- Más archivos y código de mapeo (mapper por agregado, DTOs, puertos).
- Se pierde parte de la productividad «gratis» de Eloquent en escrituras.
- Partes de la Fase 1 son casi todo framework (login, verificación de
  email): ahí la capa de dominio será fina y el valor del refactor, bajo.
  Se asume conscientemente para tener un único estilo de arquitectura.
- Las factories de los modelos movidos necesitan `#[UseFactory]`, y los tests
  existentes cambian sus `use` (permitido por Q-03).

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| Hexagonal solo en módulos nuevos | Recomendada inicialmente por menor riesgo; descartada por decisión del equipo para tener un único estilo de arquitectura. |
| `app/Modules/` en lugar de `src/` | Separa menos visualmente el núcleo del framework. |
| Usar los modelos Eloquent como entidades de dominio | Rompe A-02: el dominio dependería de Laravel y no podría probarse aislado. |
| Arquitectura en capas sin puertos | No invierte las dependencias; la infraestructura seguiría filtrándose al negocio. |
