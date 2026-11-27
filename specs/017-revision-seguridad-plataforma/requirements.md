---
spec: 017-revision-seguridad-plataforma
prefijo: RSP
estado: borrador
fecha: 2026-11-11
módulo: Platform, IdentityAccess, Students, Tenancy
depende-de: 004-identidad-global (IDG-R11), 009-estudiantes (EST-R07), 014-superadministracion, 015-rls-transaccion (RLT-R03.2)
---

# Requisitos — Cierre de la revisión de seguridad de SPEC-014

## Contexto

SPEC-014 tenía pendiente su `/security-review` (S-12). Se hizo el
2026-11-11 sobre los PR A a D, en tres áreas:

- acceso y segundo factor;
- planteles y su activación;
- consolidaciones y bitácora.

No hubo hallazgos críticos ni altos. Hubo tres medios, comprobados en el
código, y varios bajos. Esta spec los cierra antes de producción, como
SPEC-015 cerró la revisión anterior.

**Medios:**

1. **Un inicio de sesión simultáneo puede deshacer una acción de un
   Superadministrador.** Iniciar sesión o confirmar un código reescribe el
   operador entero con lo que leyó al empezar. Puede que un Superadministrador
   lo desactive, le cambie el nivel o le restablezca el segundo factor
   mientras tanto. En ese caso, la acción se pierde, aunque la bitácora diga
   que se hizo. La misma carrera deja aceptar dos veces un TOTP o un código
   de recuperación, y puede devolver a la vida códigos de recuperación ya
   regenerados.
2. **Dos resoluciones simultáneas de una consolidación pueden fusionar
   cuentas que soporte decidió mantener separadas.** El estado del caso se
   comprueba antes de aplicar la decisión y no se vuelve a comprobar. Una
   persona puede recibir así las membresías de otra.
3. **Un traslado puede llevar datos a un plantel desactivado.** Aprobar una
   solicitud pendiente copia en el destino la ficha, la salud y los
   representantes del estudiante, aunque el destino esté inactivo
   (PLT-R05.1).

**Bajos:**

4. Dos Superadministradores que se quitan el nivel o se desactivan el uno
   al otro a la vez pueden dejar la plataforma sin ninguno (PLT-R03.5).
5. La confirmación del TOTP limita los intentos solo por IP, y nada cuenta
   los fallos de un operador repartidos entre muchas IP (PLT-NF03).
6. Los códigos de recuperación guardados se pueden sacar por fuerza bruta
   con solo una copia de la base de datos.
7. La lista de casos de consolidación muestra datos personales sin quedar
   en la bitácora, y tampoco queda la vista previa que sale bien
   (PLT-R07.1).
8. El comando de consola acepta como operador cualquier correo, sin
   comprobar que quien lo ejecuta es ese operador (PLT-R06.6).
9. La vista previa obligatoria antes de resolver (PLT-R06.3) solo la exige
   la página del navegador.
10. El TOTP reciente de las acciones sensibles (PLT-NF04) solo lo comprueba
    la ruta, no el caso de uso (PLT-NF05).

## Alcance

- **Incluye:**
  - el inicio de sesión y la confirmación del código de los operadores,
    simultáneos con su gestión;
  - la regla del último Superadministrador activo con acciones
    simultáneas;
  - el límite de intentos por operador y el bloqueo tras varios códigos
    fallidos;
  - el guardado de los códigos de recuperación;
  - la resolución de consolidaciones simultáneas, desde el panel o la
    consola;
  - la vista previa exigida por el servidor;
  - la identificación del operador en el comando de consola;
  - la bitácora de la lista de casos y de las vistas previas;
  - las escrituras en un plantel inactivo hechas desde otro plantel o desde
    la cola;
  - el TOTP reciente comprobado también en los casos de uso.
- **Excluye:**
  - el acceso excepcional a datos de un plantel («break-glass»), fuera de
    alcance desde SPEC-014;
  - cambiar los valores de seguridad aprobados en SPEC-014: 30 minutos sin
    actividad, 5 intentos por minuto, TOTP de los últimos 10 minutos;
  - la solicitud de traslado hacia un estudiante de un plantel inactivo.
    Su personal no puede entrar, así que la solicitud caduca a los 15 días
    (EST-R07.7), igual que si nadie respondiera.

## Decisiones de producto incorporadas (2026-11-11)

| Pregunta | Decisión |
|----------|----------|
| Traslado pendiente cuando se desactiva el destino | La solicitud sigue pendiente. Aprobarla mientras el destino está inactivo se rechaza sin cambios. Si el plantel se reactiva antes de los 15 días, se puede aprobar; si no, caduca (EST-R07.7). |
| Fallos seguidos de código de un operador | Tras 10 códigos fallidos seguidos, desde cualquier IP, el operador queda bloqueado 15 minutos. Se avisa por correo al operador y a los Superadministradores activos. |
| Operador en la consola | El comando pide de forma interactiva un TOTP del operador nombrado. Sin código válido, se rechaza y se registra el rechazo. |
| Vista previa | La exige también el servidor: del mismo operador, del mismo caso y con la misma decisión, de los últimos 10 minutos. |

## Glosario

| Término | Significado |
|---------|-------------|
| Acción de gestión | Desactivar o reactivar un operador, cambiarle el nivel o restablecerle el segundo factor (PLT-R03). |
| Código | Un TOTP o un código de recuperación (PLT-R01.2). |
| Código fallido | Un código incorrecto o ya usado. Al iniciar sesión, cuenta solo si la contraseña era correcta. |
| Bloqueo | Los 15 minutos en que un operador no puede iniciar sesión ni confirmar un código, tras 10 códigos fallidos seguidos. |
| Caso | Las cuentas de un mismo correo marcadas para revisión (IDG-R09.5, IDG-R11). |
| Vista previa | Lo que hará la resolución de un caso, mostrado sin aplicar nada (PLT-R06.3, IDG-R11.4). |
| Plantel inactivo | Un plantel desactivado desde el panel (PLT-R05.1). |

## Requisitos

### RSP-R01 — Acceso de un operador simultáneo con su gestión

**Historia:** Como Superadministrador, quiero que cuando desactivo a un
operador, le cambio el nivel o le restablezco el segundo factor, el cambio
se mantenga aunque ese operador esté entrando justo en ese momento.

**Criterios de aceptación (EARS):**

- **RSP-R01.1** — Si una acción de gestión sobre un operador se aplica
  mientras ese operador inicia sesión o confirma un código, entonces el
  sistema deberá conservar el resultado de la acción de gestión.
- **RSP-R01.2** — Si un operador desactivado, o con el segundo factor
  restablecido, estaba iniciando sesión cuando se aplicó la acción, entonces
  el sistema no deberá dejarle ninguna sesión abierta.
- **RSP-R01.3** — Si un operador cambia de nivel mientras inicia sesión,
  entonces el sistema no deberá dejarle una sesión con el nivel anterior.
- **RSP-R01.4** — Si dos inicios de sesión o confirmaciones presentan a la
  vez el mismo TOTP del mismo operador, entonces el sistema deberá aceptar
  como mucho uno (PLT-NF03).
- **RSP-R01.5** — Si dos inicios de sesión presentan a la vez el mismo
  código de recuperación, entonces el sistema deberá aceptar como mucho uno
  (PLT-R01.5).
- **RSP-R01.6** — Cuando un operador termina de regenerar sus códigos de
  recuperación, el sistema deberá aceptar solo los nuevos, aunque a la vez
  se estuviera usando uno de los anteriores (PLT-R01.10).

### RSP-R02 — Último Superadministrador activo con acciones simultáneas

**Historia:** Como responsable de la plataforma, quiero que nunca se quede
sin ningún Superadministrador activo, aunque dos de ellos actúen a la vez.

**Criterios de aceptación (EARS):**

- **RSP-R02.1** — Si varias acciones simultáneas de desactivar o quitar el
  nivel dejarían juntas la plataforma sin ningún Superadministrador activo,
  entonces el sistema deberá rechazar sin cambios las que lo harían, y
  aplicar solo las que dejan al menos uno (PLT-R03.5).
- **RSP-R02.2** — Cuando se rechaza una acción por esta regla, el sistema
  deberá mostrar el mismo motivo que PLT-R03.5.

### RSP-R03 — Límite de intentos y bloqueo por operador

**Historia:** Como responsable de la plataforma, quiero que nadie que tenga
la contraseña de un operador, o una sesión suya robada, pueda adivinar sus
códigos repartiendo intentos entre muchas IP.

**Criterios de aceptación (EARS):**

- **RSP-R03.1** — El sistema deberá limitar la confirmación del TOTP en una
  sesión abierta a 5 intentos por minuto por operador e IP, igual que el
  inicio de sesión (PLT-NF03).
- **RSP-R03.2** — Cuando un operador acumula 10 códigos fallidos seguidos,
  entre el inicio de sesión y la confirmación y desde cualquier IP, el
  sistema deberá bloquearlo durante 15 minutos.
- **RSP-R03.3** — Si al iniciar sesión la contraseña es incorrecta,
  entonces el sistema no deberá contar el intento como código fallido.
- **RSP-R03.4** — Mientras un operador está bloqueado, el sistema deberá
  rechazar su inicio de sesión con el mensaje único de PLT-R01.3, aunque la
  contraseña y el código sean correctos.
- **RSP-R03.5** — Cuando un operador queda bloqueado, el sistema deberá
  cerrar en el servidor todas sus sesiones abiertas, como al desactivarlo
  (PLT-R03.4).
- **RSP-R03.6** — Cuando un operador queda bloqueado, el sistema deberá
  avisarle por correo, y avisar también a los Superadministradores activos,
  con la fecha y las IP de los intentos.
- **RSP-R03.7** — Cuando un operador queda bloqueado, el sistema deberá
  registrarlo en la bitácora de plataforma.
- **RSP-R03.8** — Cuando un operador presenta un código correcto fuera de
  un bloqueo, el sistema deberá poner a cero su cuenta de códigos fallidos
  seguidos.
- **RSP-R03.9** — Cuando terminan los 15 minutos, el sistema deberá volver
  a aceptar los códigos del operador, con la cuenta de fallos a cero.
- **RSP-R03.10** — El sistema no deberá ofrecer a nadie, tampoco a un
  Superadministrador, una forma de levantar un bloqueo antes de los 15
  minutos, salvo el restablecimiento del segundo factor (RSP-R03.11).
- **RSP-R03.11** — Cuando un Superadministrador restablece el segundo
  factor de un operador bloqueado (PLT-R03.7), el sistema deberá levantar
  el bloqueo y poner a cero su cuenta de fallos, para que configure un TOTP
  nuevo en su próximo acceso.

### RSP-R04 — Resolución simultánea de un caso de consolidación

**Historia:** Como persona con varias cuentas históricas, quiero que, si
soporte decide que mis cuentas son de personas distintas, nadie pueda
fusionarlas después, aunque otra resolución llegue a la vez.

**Criterios de aceptación (EARS):**

- **RSP-R04.1** — Si dos resoluciones del mismo caso llegan a la vez, desde
  el panel, desde la consola o desde ambos, entonces el sistema deberá
  aplicar como mucho una.
- **RSP-R04.2** — Si una resolución llega cuando otra del mismo caso ya se
  aplicó, entonces el sistema deberá rechazarla sin cambios y registrar el
  rechazo (PLT-R06.4, IDG-R11.5).
- **RSP-R04.3** — El sistema nunca deberá consolidar cuentas que soporte
  decidió mantener separadas, sea cual sea el orden en que lleguen las
  resoluciones.
- **RSP-R04.4** — Si una consolidación se rechaza por esta regla, entonces
  el sistema no deberá enviar el correo de cuentas consolidadas ni cerrar
  ninguna sesión.

### RSP-R05 — Vista previa exigida por el servidor

**Historia:** Como responsable de la plataforma, quiero que ningún operador
pueda resolver un caso sin haber visto antes lo que hará la resolución.

**Criterios de aceptación (EARS):**

- **RSP-R05.1** — Cuando un operador resuelve un caso desde el panel, el
  sistema deberá exigir una vista previa de ese operador, del mismo caso y
  con la misma decisión, hecha en los últimos 10 minutos (PLT-R06.3).
- **RSP-R05.2** — Si no hay esa vista previa, o caducó, entonces el sistema
  deberá rechazar la resolución sin cambios, registrar el rechazo y pedir la
  vista previa de nuevo.
- **RSP-R05.3** — Cuando una resolución se aplica, el sistema deberá dejar
  sin validez la vista previa que la autorizó.
- **RSP-R05.4** — Si las cuentas enviadas no son las del caso de la
  dirección del panel en que se resuelve, entonces el sistema deberá
  rechazar la resolución sin cambios.

### RSP-R06 — El operador del comando de consola

**Historia:** Como responsable de la plataforma, quiero que nadie con acceso
al servidor pueda resolver un caso en nombre de otro operador.

**Criterios de aceptación (EARS):**

- **RSP-R06.1** — Cuando el comando de resolución nombra a un operador, el
  sistema deberá pedir de forma interactiva un TOTP de ese operador antes de
  simular o aplicar nada (PLT-R06.6, IDG-R11.2).
- **RSP-R06.2** — Si el TOTP es incorrecto o ya se usó, o el operador no
  está activo o está bloqueado, entonces el comando deberá negarse sin
  cambios y registrar el rechazo en la bitácora de plataforma (IDG-R11.3).
- **RSP-R06.3** — El sistema no deberá aceptar el TOTP como argumento ni
  como opción del comando.
- **RSP-R06.4** — Si el comando se ejecuta sin una terminal interactiva,
  entonces deberá negarse sin cambios y registrar el rechazo.
- **RSP-R06.5** — Cuando un TOTP falla en el comando, el sistema deberá
  contarlo como código fallido del operador (RSP-R03.2).
- **RSP-R06.6** — El sistema deberá aceptar cada TOTP una sola vez, entre
  el panel y la consola: un código usado en uno no vale en el otro
  (PLT-NF03).
- **RSP-R06.7** — Si en el comando se presenta un código de recuperación en
  lugar de un TOTP, entonces el comando deberá negarse sin cambios. Los
  códigos de recuperación solo sirven para entrar al panel (PLT-R01.5).
- **RSP-R06.8** — Antes de aplicar una resolución, el comando deberá
  mostrar los cambios que hará y pedir una confirmación expresa. Sin un
  «sí», no deberá aplicar nada (equivalente de RSP-R05 en la consola).

### RSP-R07 — Las consultas de casos quedan en la bitácora

**Historia:** Como Superadministrador, quiero saber quién vio los datos de
las personas con casos de consolidación, también desde la lista.

**Criterios de aceptación (EARS):**

- **RSP-R07.1** — Cuando un operador abre la lista de casos, el sistema
  deberá registrarlo en la bitácora de plataforma, con el operador, la
  fecha, la IP y los identificadores de las cuentas mostradas (PLT-R07.1).
- **RSP-R07.2** — Cuando una vista previa termina con éxito, desde el
  panel o desde la consola, el sistema deberá registrarla con el operador,
  la decisión y los identificadores de las cuentas.
- **RSP-R07.3** — El sistema no deberá guardar en esas entradas nombres,
  correos ni planteles de las cuentas, solo sus identificadores
  (PLT-R07.3, IDG-R11.7).

### RSP-R08 — Escrituras en un plantel inactivo

**Historia:** Como plantel desactivado, quiero que nadie escriba datos
nuevos en mí mientras estoy cerrado, ni desde otro plantel ni desde un
proceso en segundo plano.

**Criterios de aceptación (EARS):**

- **RSP-R08.1** — Si el origen aprueba una solicitud de traslado cuando el
  destino está inactivo, entonces el sistema deberá rechazar la aprobación
  sin cambios en ninguno de los dos planteles. El estudiante deberá seguir
  activo en el origen (EST-R07.5).
- **RSP-R08.2** — Cuando se rechaza la aprobación por esta regla, el
  sistema deberá decir al origen que el destino no puede recibir el traslado
  por ahora, sin el motivo de la desactivación.
- **RSP-R08.3** — Mientras el destino está inactivo, el sistema deberá
  mantener la solicitud pendiente, con su plazo original de 15 días
  (EST-R07.7).
- **RSP-R08.4** — Cuando el destino se reactiva antes de que caduque la
  solicitud, el sistema deberá permitir aprobarla.
- **RSP-R08.5** — Si un trabajo de la cola va a actuar sobre un plantel
  (RLT-R03.2) y ese plantel está inactivo cuando se ejecuta, entonces el
  sistema deberá descartarlo sin escribir nada en él y sin reintentarlo, y
  dejar constancia en el registro de errores con el trabajo y el
  identificador del plantel.
- **RSP-R08.6** — El sistema deberá seguir permitiendo las lecturas de
  plataforma sobre un plantel inactivo (PLT-R04.3).

## Requisitos no funcionales

- **RSP-NF01** (seguridad) — Cada carrera de RSP-R01, R02 y R04 deberá
  tener un test que la provoque con dos conexiones a PostgreSQL a la vez, y
  pasar en CI. Un test que solo simule el orden en un mismo proceso no
  basta.
- **RSP-NF02** (seguridad) — Los códigos de recuperación guardados no
  deberán poder sacarse con solo una copia de la base de datos: su huella
  deberá depender de un secreto del entorno que no está en ella (S-07,
  PLT-NF02). Los códigos vigentes al desplegar deberán seguir valiendo, sin
  que ningún operador tenga que regenerarlos.
- **RSP-NF03** (seguridad) — Las acciones que exigen un TOTP de los
  últimos 10 minutos deberán comprobarlo también en su caso de uso, no solo
  en la ruta (S-04, PLT-NF04, PLT-NF05). Son: activar o desactivar un
  plantel, gestionar operadores y resolver un caso.
- **RSP-NF04** (seguridad) — Ni `BYPASSRLS` ni funciones `SECURITY DEFINER`
  nuevas (S-03, PLT-NF01).
- **RSP-NF05** (privacidad) — Ni el bloqueo ni ningún rechazo de esta spec
  deberán revelar si un correo es de un operador (S-05), ni el motivo de la
  desactivación de un plantel a otro plantel.
- **RSP-NF06** (rendimiento) — Se deberán seguir cumpliendo los umbrales de
  PLT-NF06 y RLT-NF03.
- **RSP-NF07** (fiabilidad) — Los tests existentes de SPEC-009, 014 y 015
  deberán seguir en verde sin cambiar su lógica, solo su preparación
  (Q-03). Las excepciones son los que esta spec modifica de forma expresa:
  la consola de IDG-R11 y la resolución sin vista previa.

## Aislamiento entre planteles (S-01, S-02)

Esta spec no crea tablas con datos de un plantel. Lo que guarda de nuevo
(fallos y bloqueo de un operador, vistas previas) es de plataforma y vive
fuera de la RLS de los planteles, como el resto de SPEC-014.

RSP-R08 refuerza el aislamiento: ningún plantel ni proceso escribe en un
plantel inactivo. El plantel de cada escritura sigue saliendo del
subdominio o del trabajo encolado (S-02, RLT-R03.2), nunca de la petición.

## Cambios en otras specs

Se aplican en el mismo PR que los implemente (P-04):

- **SPEC-014:**
  - PLT-NF03 añade el límite por operador en la confirmación y el bloqueo
    (RSP-R03);
  - PLT-NF02 añade la huella con secreto (RSP-NF02);
  - PLT-R03.5 añade el caso simultáneo (RSP-R02);
  - PLT-R03.7 añade que el restablecimiento levanta el bloqueo
    (RSP-R03.11);
  - PLT-R06.3 pasa a exigirse en el servidor (RSP-R05);
  - PLT-R06.6 añade el TOTP en la consola (RSP-R06);
  - PLT-R07.1 precisa la lista de casos y la vista previa (RSP-R07);
  - se marca el `/security-review` de `tasks.md`.
- **SPEC-004:** IDG-R11.2 y R11.3 añaden el TOTP interactivo, sin códigos
  de recuperación (RSP-R06). IDG-R11.4 añade la confirmación expresa antes
  de aplicar (RSP-R06.8). IDG-R11.5 añade el caso simultáneo (RSP-R04).
- **SPEC-009:** EST-R07.5 añade la excepción del destino inactivo
  (RSP-R08.1 – R08.4).
- **SPEC-015:** RLT-R03.2 añade el plantel inactivo (RSP-R08.5).
- **Roadmap:** se anota la revisión de SPEC-014 y su cierre.

## Fuera de alcance y preguntas abiertas

Resueltas el 2026-11-11:

- [x] **Sesiones abiertas al bloquear:** se cierran, como al desactivar
  (RSP-R03.5). Una sesión robada no sigue abierta mientras se adivinan
  códigos.
- [x] **Desbloqueo anticipado:** no existe (RSP-R03.10). Si la urgencia es
  real, el camino es restablecer el segundo factor, que levanta el bloqueo y
  deja una traza más fuerte (RSP-R03.11).
- [x] **Códigos de recuperación en la consola:** no se aceptan, solo el
  TOTP (RSP-R06.7).
- [x] **Confirmación en la consola:** el comando muestra el plan y pide un
  «sí» expreso antes de aplicar (RSP-R06.8). `--dry-run` sigue igual
  (IDG-R11.4).
- [x] **Trabajo encolado para un plantel inactivo:** se descarta sin
  reintentar y queda en el registro de errores (RSP-R08.5).
- [x] **Códigos de recuperación vigentes al desplegar:** siguen valiendo
  (RSP-NF02).
- [x] **ADR antes del diseño** (P-06): cómo se cierran las carreras de
  RSP-R01, R02 y R04. Alternativas: bloqueo de filas en la transacción,
  escrituras condicionales o versión del agregado.

Sin preguntas abiertas.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| RSP-R01.1 | |
| RSP-R01.2 | |
| RSP-R01.3 | |
| RSP-R01.4 | |
| RSP-R01.5 | |
| RSP-R01.6 | |
| RSP-R02.1 | |
| RSP-R02.2 | |
| RSP-R03.1 | |
| RSP-R03.2 | |
| RSP-R03.3 | |
| RSP-R03.4 | |
| RSP-R03.5 | |
| RSP-R03.6 | |
| RSP-R03.7 | |
| RSP-R03.8 | |
| RSP-R03.9 | |
| RSP-R03.10 | |
| RSP-R03.11 | |
| RSP-R04.1 | |
| RSP-R04.2 | |
| RSP-R04.3 | |
| RSP-R04.4 | |
| RSP-R05.1 | |
| RSP-R05.2 | |
| RSP-R05.3 | |
| RSP-R05.4 | |
| RSP-R06.1 | |
| RSP-R06.2 | |
| RSP-R06.3 | |
| RSP-R06.4 | |
| RSP-R06.5 | |
| RSP-R06.6 | |
| RSP-R06.7 | |
| RSP-R06.8 | |
| RSP-R07.1 | |
| RSP-R07.2 | |
| RSP-R07.3 | |
| RSP-R08.1 | |
| RSP-R08.2 | |
| RSP-R08.3 | |
| RSP-R08.4 | |
| RSP-R08.5 | |
| RSP-R08.6 | |
| RSP-NF01 | |
| RSP-NF02 | |
| RSP-NF03 | |
| RSP-NF04 | |
| RSP-NF05 | |
| RSP-NF06 | |
| RSP-NF07 | |
