---
estado: aceptada (retroactiva, F1-02)
fecha: 2026-10-10
---

# ADR 0003 — Row Level Security como límite de seguridad multi-tenant

## Contexto

Todas las instituciones comparten una base de datos y se aíslan por
`institution_id`. Un filtro olvidado en una consulta (o un `DB::select` crudo)
expondría datos de un colegio a otro.

## Decisión

(Tomada en F1-02, registrada aquí.)

- Cada tabla con datos de una institución tiene `FORCE ROW LEVEL SECURITY` y
  la política `tenant_isolation`, que compara `institution_id` con la variable
  de sesión `app.current_institution_id`.
- La aplicación se conecta con un rol `NOSUPERUSER NOBYPASSRLS` que es dueño
  de las tablas (por eso hace falta `FORCE`).
- Sin institución en la sesión, una tabla se ve vacía y rechaza toda escritura.
- El scope de Eloquent es una comodidad, no la protección.

## Consecuencias

- El aislamiento no depende de que cada consulta recuerde filtrar.
- Los tests deben correr con el mismo rol restringido (CI ejecuta
  `docker/postgres/init.sql`).
- Procesos largos (colas, comandos que recorren instituciones) deben fijar y
  limpiar la institución explícitamente.
- La arquitectura hexagonal (ADR 0001) no sustituye a la RLS: la capa
  `Tenancy\Infrastructure` la encapsula, pero sigue siendo el límite.
