---
spec: 015-rls-transaccion
prefijo: RLT
estado: aprobada
fecha: 2026-10-26
---

# Requisitos — RLS por transacción y cierre de la revisión de seguridad

## Contexto

La revisión de seguridad del 2026-10-23 ([roadmap](../roadmap.md#revisión-de-seguridad))
dejó cuatro hallazgos por debajo del umbral, para resolverlos antes de
producción. Esta spec los cierra.

1. **El plantel activo dura toda la conexión.** El plantel de la RLS
   (ADR 0003) se fija en la conexión a PostgreSQL y dura hasta que alguien
   lo cambie. Con conexiones persistentes, con un pool en modo transacción
   (PgBouncer) o con procesos de larga vida (trabajadores de la cola), una
   petición o un trabajo puede heredar el plantel del anterior. La RLS
   seguiría filtrando, pero por el plantel equivocado. Eso rompe el límite
   de aislamiento entre planteles (S-01).
2. **Login CSRF con el enlace de traspaso.** Un miembro de un plantel puede
   enviar a otra persona su propio enlace de traspaso (IDG-R03.7, IDG-NF03).
   Si esa persona lo abre, entra con la sesión del atacante, y lo que haga
   queda en esa cuenta.
3. **La política `UPDATE` de `transfer_requests`** (ADR 0008) deja al
   plantel de destino cambiar el plantel de origen de su propia solicitud.
   La aplicación no lo expone, pero la base de datos no lo impide.
4. **Cuentas históricas sin consolidar con el mismo correo** (IDG-R09).
   Cada una puede aceptar invitaciones a ese correo antes de la
   consolidación. Así se reparten membresías nuevas entre cuentas que quizá
   no son de la misma persona.

**Decisión de alcance** (2026-10-26): el aislamiento debe valer con
cualquier forma de conexión en producción (persistente, PgBouncer en modo
transacción, procesos de larga vida). Cubre las peticiones web, la cola y la
consola.

## Alcance

- **Incluye:**
  - el plantel activo de la base de datos, que nunca pasa de una petición,
    trabajo o comando a otro, ni de una transacción a otra en una conexión
    compartida;
  - los cambios de plantel dentro de una misma petición: traslados (ADR
    0008), vínculo de representante, aceptación de invitaciones, lecturas
    de plataforma (ADR 0010) y consolidación de cuentas;
  - el enlace de traspaso, ligado al navegador que lo pidió;
  - la política de escritura de `transfer_requests`;
  - las invitaciones a un correo pendiente de consolidación o en revisión.
- **Excluye:**
  - montar PgBouncer u otra infraestructura en producción (F1-38). Esta spec
    solo exige que la aplicación sea correcta con ella;
  - Laravel Octane: no está instalado. Si se adopta, deberá cumplir esta
    spec;
  - cambiar el modelo de RLS (ADR 0003, 0006): sigue igual, salvo dónde vive
    el plantel activo.

## Glosario

| Término | Significado |
|---------|-------------|
| Plantel activo | El plantel con el que la base de datos aplica la RLS a las sentencias de una unidad de trabajo. |
| Unidad de trabajo | Una petición web, un trabajo de la cola o una ejecución de un comando de consola. |
| Conexión compartida | Una conexión a PostgreSQL que atiende varias unidades de trabajo seguidas: persistente, de un pool o de un proceso de larga vida. |
| Pool en modo transacción | Un intermediario (p. ej. PgBouncer) que asigna una conexión real por transacción, no por cliente. Dos sentencias seguidas de la misma petición pueden ir por conexiones distintas. |
| Enlace de traspaso | El enlace de un solo uso que lleva del dominio central al plantel elegido sin pedir otra vez la contraseña (IDG-R03.7). |
| Correo pendiente de consolidación | Un correo con varias cuentas históricas aún no unificadas (IDG-R09.2), pendiente o marcado para revisión (IDG-R09.5). |

## Requisitos

### RLT-R01 — El plantel activo no pasa de una unidad de trabajo a otra

**Historia:** Como plantel, quiero que ninguna petición ni proceso vea o
escriba mis datos con el plantel de otro, ni los de otro con el mío, sea
cual sea la forma de conectar con la base de datos.

**Criterios de aceptación (EARS):**

- **RLT-R01.1** — Mientras se atiende una petición de un plantel, el sistema
  deberá aplicar la RLS de ese plantel a cada lectura y escritura de datos de
  planteles, también a las que se hacen fuera de una transacción explícita
  del código.
- **RLT-R01.2** — Cuando termina una unidad de trabajo, con éxito o con
  error, el sistema no deberá dejar ningún plantel activo en la conexión que
  usó.
- **RLT-R01.3** — Si una conexión compartida atendió antes a otro plantel,
  entonces una unidad de trabajo nueva no deberá ver ni escribir ninguna
  fila de ese plantel.
- **RLT-R01.4** — Si dos sentencias de una misma petición van por conexiones
  distintas de un pool en modo transacción, entonces el sistema deberá
  aplicar a cada una el plantel de la petición, y no el de otra.
- **RLT-R01.5** — Si una transacción falla y se revierte, entonces el
  sistema deberá seguir aplicando el plantel de la petición a las sentencias
  siguientes de esa misma petición.
- **RLT-R01.6** — Mientras no hay plantel activo (dominio central, panel de
  plataforma, una unidad de trabajo sin plantel), el sistema deberá seguir
  sin ver ni aceptar filas de las tablas de planteles (TEN-R05.1, R05.2).

### RLT-R02 — Cambios de plantel dentro de una unidad de trabajo

**Historia:** Como plantel, quiero que un caso de uso que actúa por un
momento en otro plantel (traslados, vínculos, invitaciones, lecturas de
plataforma) no deje ese otro plantel activo para lo que sigue.

**Criterios de aceptación (EARS):**

- **RLT-R02.1** — Cuando un caso de uso actúa por un momento en otro
  plantel, el sistema deberá volver al plantel anterior, o a ninguno, al
  terminar, también si falla.
- **RLT-R02.2** — Mientras dura ese cambio, el sistema deberá aplicar la RLS
  del otro plantel solo a las sentencias de ese caso de uso, y nunca a las de
  otra petición que comparta la conexión.
- **RLT-R02.3** — Si al terminar un cambio de plantel queda activo un
  plantel distinto del anterior, entonces el sistema deberá fallar de forma
  visible, sin confirmar lo escrito, en lugar de seguir con el plantel
  equivocado.

### RLT-R03 — Cola y consola

**Historia:** Como plantel, quiero que los procesos en segundo plano traten
mis datos solo cuando lo piden de forma expresa, y nunca con el plantel que
dejó el proceso anterior.

**Criterios de aceptación (EARS):**

- **RLT-R03.1** — Cuando empieza un trabajo de la cola, el sistema deberá
  ejecutarlo sin plantel activo, aunque el trabajador haya atendido antes a
  otro plantel.
- **RLT-R03.2** — Donde un trabajo de la cola actúe sobre un plantel, el
  sistema deberá activar ese plantel de forma expresa, guardado con el
  trabajo al encolarlo, y nunca tomarlo del proceso que lo ejecuta.
- **RLT-R03.3** — Cuando un comando de consola recorre varios planteles, el
  sistema deberá aplicar a cada uno solo su RLS, sin que lo hecho en uno se
  vea ni se escriba con el plantel de otro.

### RLT-R04 — El enlace de traspaso solo vale en el navegador que lo pidió

**Historia:** Como persona con cuenta, quiero que nadie pueda meterme en su
sesión enviándome su enlace de traspaso.

**Criterios de aceptación (EARS):**

- **RLT-R04.1** — Cuando una persona elige un plantel en el dominio central,
  el sistema deberá emitir un enlace de traspaso que solo valga para la
  sesión central de ese navegador, además de la cuenta y el plantel
  (IDG-NF03).
- **RLT-R04.2** — Si un enlace de traspaso se abre en un navegador sin esa
  sesión central (otro navegador, otra persona, una ventana privada),
  entonces el sistema deberá rechazarlo sin abrir ninguna sesión, igual que
  un enlace caducado o usado. Deberá llevar al inicio de sesión central con
  el plantel ya elegido.
- **RLT-R04.3** — El sistema no deberá revelar en el rechazo por qué no
  valió el enlace.
- **RLT-R04.4** — Si un enlace de traspaso se abre en un navegador donde ya
  hay otra cuenta en ese plantel, entonces el sistema no deberá sustituir esa
  sesión por la del enlace.

### RLT-R05 — Solicitudes de traslado: el origen no se cambia

**Historia:** Como plantel de origen de un traslado, quiero que ningún otro
plantel pueda presentarme como origen de una solicitud que no respondí.

**Criterios de aceptación (EARS):**

- **RLT-R05.1** — Si el plantel de destino intenta fijar o cambiar el
  plantel de origen de su solicitud, entonces la base de datos deberá
  rechazar la escritura, también con SQL directo.
- **RLT-R05.2** — Cuando el plantel de origen responde una solicitud
  pendiente, la base de datos deberá aceptar como origen solo al propio
  plantel que responde.
- **RLT-R05.3** — Si alguien intenta cambiar el origen de una solicitud ya
  respondida, entonces la base de datos deberá rechazar la escritura.
- **RLT-R05.4** — El sistema deberá seguir permitiendo, sin cambios, las
  escrituras legítimas de SPEC-009: crear, responder, cancelar y caducar
  solicitudes (EST-R07).

### RLT-R06 — Invitaciones a un correo pendiente de consolidación

**Historia:** Como plantel, quiero que mis invitaciones a un correo con
cuentas históricas sin unificar no acaben repartidas entre cuentas que quizá
son de personas distintas.

**Criterios de aceptación (EARS):**

- **RLT-R06.1** — Si una cuenta cuyo correo está pendiente de consolidación
  intenta aceptar una invitación, entonces el sistema deberá rechazarlo sin
  crear la membresía, y ofrecerle consolidar sus cuentas primero
  (IDG-R09.4).
- **RLT-R06.2** — Si el correo está marcado para revisión (IDG-R09.5),
  entonces el sistema deberá rechazar la aceptación sin crear la membresía,
  e indicar que soporte está revisando sus cuentas.
- **RLT-R06.3** — Mientras se rechaza por consolidación pendiente, el
  sistema deberá conservar la invitación válida, con su plazo original, para
  aceptarla después.
- **RLT-R06.4** — Cuando el correo se consolida, o soporte decide mantener
  las cuentas separadas (IDG-R11), el sistema deberá permitir aceptar la
  invitación con la cuenta resultante, o con la que tenga ese correo de
  forma única.
- **RLT-R06.5** — Si soporte mantiene separadas las cuentas de un correo,
  entonces el sistema deberá seguir rechazando la aceptación de las
  invitaciones a ese correo e indicar que debe contactar con el plantel.

## Requisitos no funcionales

- **RLT-NF01** (seguridad) — La suite de aislamiento (`--group=rls`) deberá
  pasar también contra un PgBouncer en modo transacción en CI, además de
  contra PostgreSQL directo.
- **RLT-NF02** (seguridad) — Ni `BYPASSRLS` ni funciones `SECURITY DEFINER`
  nuevas (S-03). El rol de la aplicación sigue siendo
  `NOSUPERUSER NOBYPASSRLS`.
- **RLT-NF03** (rendimiento) — El coste añadido por fijar el plantel en cada
  transacción no deberá superar 5 ms en el p95 de una petición de un
  plantel. Los umbrales de rendimiento existentes (AUD-NF, NAV-NF02,
  PLT-NF06) deberán seguir cumpliéndose.
- **RLT-NF04** (fiabilidad) — Los tests existentes de aislamiento,
  traslados, traspaso e invitaciones deberán seguir en verde sin cambiar su
  lógica, solo su preparación (Q-03), salvo los que esta spec modifica
  expresamente.
- **RLT-NF05** (privacidad) — Ningún rechazo de esta spec (traspaso,
  invitación) deberá revelar datos de otra cuenta ni de otro plantel.

## Cambios en otras specs

Se aplican en el mismo PR que los implemente (P-04):

- **SPEC-001 (TEN-R05):** el plantel activo pasa de la conexión a la
  transacción. TEN-R05.6 se precisa con RLT-R01.2 y RLT-R01.5.
- **SPEC-004:**
  - IDG-NF03 añade «ligado a la sesión central que lo pidió» (RLT-R04.1);
  - IDG-R04.5 añade la excepción de RLT-R06.
- **ADR 0003 y ADR 0006:** se actualizan con el nuevo ADR, que decide dónde
  vive el plantel activo.
- **ADR 0008:** cambia el `WITH CHECK` de la política `UPDATE` de
  `transfer_requests` (RLT-R05).
- **ADR 0010:** `TenantScopedReader` ya no tiene que deshacer un ajuste de
  la conexión; sigue siendo de solo lectura y se revierte siempre.
- **Roadmap:** se cierran los cuatro hallazgos de la revisión del
  2026-10-23.

## Fuera de alcance y preguntas abiertas

Resueltas al aprobar los requisitos (2026-10-26):

- [x] **ADR antes del diseño** (P-06): dónde vive el plantel activo. Lo
  decide el ADR 0011, que compara:
  - una transacción por petición con `SET LOCAL`;
  - fijarlo al abrir cada transacción, y envolver las sentencias sueltas;
  - limpiarlo al terminar cada unidad de trabajo sin cambiar a transacción,
    que no cumple RLT-R01.4.

  El ADR también mide el coste (RLT-NF03).
- [x] **Trabajos en cola con plantel:** se construye el mecanismo de
  RLT-R03.2 (el plantel viaja con el trabajo y se activa de forma
  expresa), probado con un trabajo de prueba, aunque hoy ningún trabajo
  real lo use.
- [x] **PgBouncer en CI (RLT-NF01):** aprobado. Se añade un servicio
  PgBouncer en modo transacción a CI y a `docker-compose.yml`.
- [x] **RLT-R06.5:** aprobado como está. Si soporte mantiene separadas las
  cuentas de un correo, las invitaciones a ese correo siguen sin poder
  aceptarse y se indica contactar con el plantel.
- [x] **Sesiones abiertas al desplegar:** los enlaces de traspaso emitidos
  antes del despliegue dejan de valer (duran 60 s). No hay migración de
  datos.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| RLT-R01.1 | `tests/Isolation/TenantPerTransactionTest.php` › «a request of an institution sees its own rows, even outside any transaction of its code»; `tests/Feature/Tenancy/RequestTransactionTest.php` › «the request transaction opens before authentication or route bindings read the database»; `tests/Feature/Tenancy/RequestTransactionTest.php` › «a request of an institution can not answer with a stream» |
| RLT-R01.2 | `tests/Isolation/TenantPerTransactionTest.php` › «after a request the connection is left with no institution, and sees nothing of it»; `tests/Isolation/TenantPerTransactionTest.php` › «a request that fails with a server error leaves no institution behind»; `tests/Isolation/TenantActivationTest.php` › «the setting disappears when its transaction ends»; `tests/Architecture/LayersTest.php` › «no code sets the institution for a whole connection» |
| RLT-R01.3 | `tests/Isolation/TenantPerTransactionTest.php` › «after a request the connection is left with no institution, and sees nothing of it»; `tests/Isolation/TenantPerTransactionTest.php` › «a request of another institution on the same connection sees nothing of the first» |
| RLT-R01.4 | |
| RLT-R01.5 | `tests/Isolation/TenantPerTransactionTest.php` › «a savepoint rolled back inside a request keeps the institution of the request» |
| RLT-R01.6 | `tests/Isolation/TenantPerTransactionTest.php` › «the central domain, right after a request of an institution, sees nothing» |
| RLT-R02.1 | `tests/Feature/Tenancy/Infrastructure/TenantWithinTest.php` › «within() acts in the other institution, then gives back the previous one»; `tests/Feature/Tenancy/Infrastructure/TenantWithinTest.php` › «within() gives back the previous institution, or none, also when it fails»; `tests/Isolation/TenantActivationTest.php` › «within() outside any transaction opens its own and leaves no institution after it»; `tests/Architecture/LayersTest.php` › «no code activates an institution by hand; requests use forRequest() and switches within()» |
| RLT-R02.2 | `tests/Isolation/TenantSwitchTest.php` › «a switch that fails half way gives the request its institution back, and writes nothing in the other»; `tests/Isolation/TenantActivationTest.php` › «activating an institution outside any transaction is refused» |
| RLT-R02.3 | `tests/Feature/Tenancy/Infrastructure/TenantWithinTest.php` › «an institution left changed after within() rolls everything back and fails loudly» |
| RLT-R03.1 | `tests/Isolation/QueueTenantTest.php` › «a job starts with no institution, even when the previous one left a transaction open with one» |
| RLT-R03.2 | `tests/Isolation/QueueTenantTest.php` › «a job about an institution runs in it, and leaves no institution after it»; `tests/Isolation/QueueTenantTest.php` › «a job queued by a request runs only after the request commits» |
| RLT-R03.3 | `tests/Isolation/ConsoleTenantTest.php` › «going through the institutions of an account applies to each only its own Row Level Security» |
| RLT-R04.1 | |
| RLT-R04.2 | |
| RLT-R04.3 | |
| RLT-R04.4 | |
| RLT-R05.1 | |
| RLT-R05.2 | |
| RLT-R05.3 | |
| RLT-R05.4 | |
| RLT-R06.1 | |
| RLT-R06.2 | |
| RLT-R06.3 | |
| RLT-R06.4 | |
| RLT-R06.5 | |
| RLT-NF01 | |
| RLT-NF02 | `tests/Architecture/LayersTest.php` › «no code sets the institution for a whole connection»; `tests/Architecture/LayersTest.php` › «only the activator writes the institution Row Level Security applies» |
| RLT-NF03 | `tests/Isolation/RlsOverheadTest.php` › «holding the institution in the request transaction costs under 5 ms (p95)» |
| RLT-NF04 | |
| RLT-NF05 | |
