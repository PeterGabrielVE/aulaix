---
estado: propuesta
fecha: 2026-11-11
spec: 017-revision-seguridad-plataforma
---

# ADR 0014 — Carreras en operadores, consolidaciones y estado de los planteles: bloqueos consultivos por transacción

## Contexto

El `/security-review` de SPEC-014 encontró cuatro carreras, todas con el
mismo origen. Un caso de uso lee, comprueba una regla y escribe, sin que
nada impida que otra petición escriba entre la lectura y la escritura.

1. **Operadores (RSP-R01).** Iniciar sesión o confirmar un código lee el
   operador, comprueba el código y guarda el operador entero. Si en medio
   un Superadministrador lo desactiva, le cambia el nivel o le restablece
   el segundo factor, el guardado devuelve el estado anterior. La misma
   ventana deja aceptar dos veces un TOTP o un código de recuperación.
2. **Último Superadministrador (RSP-R02).** Cada acción cuenta los
   Superadministradores activos antes de su transacción. Dos acciones
   simultáneas cuentan los mismos dos.
3. **Consolidaciones (RSP-R04).** El estado del caso («en revisión») se
   comprueba antes de la transacción que fusiona las cuentas, y no se
   vuelve a comprobar. La regla no protege una fila, sino un correo: todas
   las cuentas que lo comparten.
4. **Estado del plantel de destino (RSP-R08.1).** Un traslado puede leer
   «activo» justo antes de que el panel desactive el plantel, y escribir
   después.

Hay dos restricciones:

- **Un correo no es una fila.** El caso de consolidación es el conjunto de
  cuentas con un correo. Bloquear las filas que hay no impide que aparezca
  otra con ese correo, ni ordena el bloqueo de varias filas entre dos
  peticiones.
- **Un plantel se lee desde otro.** El traslado lee el plantel de destino
  desde la transacción del origen (ADR 0008). Bloquear su fila en
  `institutions` lo acoplaría a la RLS de esa tabla.
- **ADR 0011 y PgBouncer en modo transacción:** cualquier bloqueo tiene que
  vivir en la transacción, nunca en la sesión de la conexión.

## Decisión

**Bloqueos consultivos de PostgreSQL por transacción
(`pg_advisory_xact_lock`), tomados dentro de la transacción del caso de
uso, antes de volver a leer lo que se va a comprobar.** Es el mismo
mecanismo de ADR 0007, aplicado a cuatro claves:

| Clave (`clase`, `objeto`) | La toman | Protege |
|---------------------------|----------|---------|
| (`1014`, id del operador) | inicio de sesión, confirmación del TOTP, autenticación en la consola, aceptación de la invitación, nueva inscripción del TOTP, desactivar y reactivar, cambio de nivel, restablecimiento del segundo factor, regeneración de los códigos de recuperación | RSP-R01, R03 |
| (`1015`, `0`), lista de operadores | desactivar y cambiar de nivel, **antes** que la del operador | RSP-R02 |
| (`4011`, `hashtext(lower(correo))`) | resolver un caso (panel y consola) y consolidar por uno mismo (IDG-R09) | RSP-R04 |
| (`1001`, id del plantel), compartido o exclusivo | compartido: escribir en otro plantel (traslados, trabajos de la cola); exclusivo: activar o desactivar un plantel | RSP-R08.1, R08.5 |

Tres reglas acompañan a los bloqueos:

1. **Releer bajo el bloqueo.** Lo leído antes del bloqueo solo sirve para
   saber qué bloquear. Las reglas se comprueban siempre con lo releído
   después.
2. **Orden fijo:** primero la lista de operadores, luego el operador. El
   inicio de sesión solo toma el del operador. No hay ciclos, así que no
   hay interbloqueos.
3. **El repositorio lo exige.** `OperatorRepository::save()` comprueba en
   `pg_locks` que la transacción tiene el bloqueo de ese operador. Si no lo
   tiene, falla con una `LogicException`. Un caso de uso nuevo que olvide
   bloquear falla en los tests, no en producción.

La colisión de `hashtext` entre dos correos distintos solo pone en fila dos
casos sin relación, sin efecto sobre la corrección.

## Consecuencias

**Positivas**

- Las cuatro carreras se cierran con el mismo mecanismo, probado en el
  proyecto (ADR 0007, `EnrollmentConcurrencyTest`).
- No toca la RLS (ADR 0003, 0006) ni necesita privilegios nuevos (S-03).
- Funciona con PgBouncer en modo transacción (ADR 0011): el bloqueo muere
  con la transacción.
- Los agregados siguen guardándose enteros: bajo el bloqueo, nadie más
  escribe en medio.

**Negativas**

- Es específico de PostgreSQL, como la RLS.
- El inicio de sesión de un operador espera si a la vez lo están
  gestionando. La espera es de milisegundos.
- Las claves (`clase`, `objeto`) son un espacio de nombres compartido. Se
  documentan en un único lugar del código, junto a la de ADR 0007 (`7007`).

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| `SELECT … FOR UPDATE` sobre las filas | Sirve para un operador, pero no para un correo: habría que bloquear todas sus cuentas en un orden fijo, y una cuenta nueva con ese correo quedaría fuera. Tampoco sirve para la regla de la lista de operadores sin bloquear la tabla entera. Usar dos mecanismos para el mismo problema complica la revisión. |
| Bloqueo optimista (columna `version` y comparar al guardar) | Cubre RSP-R01, pero no R02, que lee varias filas, ni R04, que lee un conjunto. Además, obliga a reintentar o a responder «inténtalo de nuevo» en el inicio de sesión. |
| Escrituras condicionales (`UPDATE … WHERE totp_last_step < ?`) | Cierra la reutilización de un código, pero no la pérdida de una acción de gestión mientras el agregado se guarde entero. Habría que partir el repositorio en escrituras por campo. |
| `SERIALIZABLE` en esas transacciones | Obliga a reintentar fallos de serialización, también en el inicio de sesión. Cambia el aislamiento de transacciones que escriben en la bitácora. Ya se descartó en ADR 0007. |
