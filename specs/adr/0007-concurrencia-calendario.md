---
estado: aceptada
fecha: 2026-10-19
fecha-aceptación: 2026-10-19
spec: 007-anio-escolar
---

# ADR 0007 — Reglas entre años escolares: bloqueo por plantel y restricciones de la base de datos

## Contexto

SPEC-007 tiene reglas que afectan a **varios años** del mismo plantel:

- como máximo un año planificado (CAL-R01.6);
- como máximo un año activo (CAL-R02.1);
- ningún solape de fechas entre años, sea cual sea su estado (CAL-R01.9);
- y, dentro de un año, exactamente un lapso abierto (CAL-R04.1).

CAL-NF02 exige que dos gestores que actúan a la vez nunca rompan estas
reglas. Si el código comprueba «no hay otro activo» y luego guarda, dos
peticiones simultáneas pueden pasar las dos la comprobación.

## Decisión

Dos capas:

1. **Bloqueo pesimista por plantel.** Cada caso de uso de escritura del
   calendario toma `pg_advisory_xact_lock` con una clave derivada del
   plantel, dentro de su transacción, antes de leer los demás años. Las
   escrituras del calendario de un mismo plantel quedan en fila. Las de
   planteles distintos no se esperan entre sí. El bloqueo se libera solo al
   terminar la transacción.
2. **Restricciones declarativas como último respaldo:**
   - índices únicos parciales: un `active` y un `planned` por plantel, y un
     lapso `open` por año;
   - `EXCLUDE USING gist (institution_id WITH =, daterange(...) WITH &&)`
     contra el solape de fechas, con la extensión `btree_gist` (de confianza
     en PostgreSQL ≥ 13: la crea el rol de la app sin privilegios
     especiales, S-03).

Si una restricción salta a pesar del bloqueo (por ejemplo, SQL escrito a
mano), la petición responde 409 y no se aplica nada.

## Consecuencias

**Positivas**

- Las reglas se cumplen aunque el código tenga un error o alguien escriba en
  la base de datos directamente.
- El dominio sigue siendo la fuente de los mensajes claros (422). La base de
  datos solo actúa en casos extremos.

**Negativas**

- Depende de `btree_gist` en el servidor de producción. Si no está
  disponible, el solape queda protegido solo por el bloqueo (plan B
  documentado en el diseño).
- El bloqueo es específico de PostgreSQL, igual que la RLS (ADR 0003).

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| Solo validación en el código | No cumple CAL-NF02 con peticiones simultáneas. |
| Bloqueo optimista (columna `version` por plantel) | Necesita una fila de «calendario del plantel» que no aporta nada al dominio, y obliga a reintentar en el cliente. |
| `SERIALIZABLE` en las transacciones del calendario | Cambia el aislamiento de toda la transacción, que también escribe en la bitácora, y obliga a reintentar fallos de serialización. |
| Trigger contra el solape | Sin bloqueo tiene la misma carrera que la validación en código, y es menos legible que una restricción declarativa. |
