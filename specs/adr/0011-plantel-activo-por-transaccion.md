---
adr: 0011
título: El plantel activo vive en la transacción, no en la conexión
estado: aceptado
fecha: 2026-10-26
spec: 015-rls-transaccion
---

# ADR 0011 — El plantel activo vive en la transacción, no en la conexión

## Contexto

La RLS (ADR 0003, 0006) filtra cada tabla de planteles con
`current_setting('app.current_institution_id')`. Hoy
`RowLevelSecurity::setInstitution` lo fija con
`set_config(..., false)`: vale **para toda la conexión** hasta que alguien
lo cambie. Se eligió así porque el ajuste tenía que sobrevivir a las
transacciones que abren Eloquent y la suite de tests.

Ese ajuste falla en tres casos que SPEC-015 exige cubrir (RLT-R01 – R03):

- **Conexiones compartidas.** Con conexiones persistentes, con un
  trabajador de la cola o con cualquier proceso de larga vida, la petición
  o el trabajo siguiente hereda el plantel si el anterior no lo limpió (por
  un error o un `exit`).
- **Pool en modo transacción (PgBouncer).** Cada transacción, y cada
  sentencia suelta, puede ir por una conexión real distinta. El ajuste hecho
  por una petición queda en una conexión que después usa otra petición de
  otro plantel. Y una sentencia de la misma petición puede caer en una
  conexión sin ajuste, o con el de otro plantel.
- **Cambios de plantel a mitad de petición.** Hoy hay seis: traslados,
  vínculo de representante, aceptar invitación, unirse a un plantel,
  consolidación y lecturas de plataforma. Cada uno restaura «a mano» con
  `activate`/`deactivate` en un `finally`. Si el `finally` falla, o si
  alguien olvida escribirlo, el plantel queda puesto.

## Decisión

### 1. Ningún ajuste de plantel dura más que su transacción

- El plantel se fija **solo** con `set_config(..., true)`, el equivalente de
  `SET LOCAL`. PostgreSQL lo deshace al terminar la transacción, con
  `COMMIT` o con `ROLLBACK`, y también al revertir a un *savepoint*. Ninguna
  conexión queda con un plantel después de una transacción.
- Una regla de arquitectura prohíbe `set_config(..., false)` sobre la
  variable del plantel.

### 2. Una transacción por petición de plantel

- `ResolveTenant` abre una transacción **antes** de activar el plantel. La
  confirma al terminar la petición si la respuesta no es un error de
  servidor (5xx) y la revierte si lo es o si escapa una excepción.
- Todo lo que la petición lee o escribe va dentro de ella: también las
  sentencias sueltas de Eloquent, los middlewares posteriores y el render de
  Inertia. Con un pool en modo transacción, la petición entera ocupa una
  única conexión real, que nadie más usa mientras tanto (RLT-R01.4).
- Las transacciones de los casos de uso pasan a ser *savepoints* dentro de
  la de la petición. Es exactamente lo que ya hace hoy la suite de tests con
  `RefreshDatabase`: el comportamiento de producción pasa a ser el que se
  prueba.
- Las peticiones sin plantel (dominio central, panel de plataforma) no
  abren transacción de petición, porque no tienen plantel que fijar. Sus
  sentencias siguen sin ver filas de planteles (RLT-R01.6).

### 3. Cambiar de plantel solo con `TenantActivator::within()`

- `within(TenantContext, callable)` abre una transacción, o un *savepoint*
  si ya hay una. Fija el plantel pedido, ejecuta el callable, vuelve a fijar
  el plantel anterior, o ninguno, y **comprueba** que el plantel activo es el
  esperado. Si no lo es, lanza una excepción dentro de la transacción, que
  se revierte (RLT-R02.3).
- Si el callable falla, revertir el *savepoint* deshace también el cambio de
  plantel. PostgreSQL lo garantiza aunque el `finally` no llegue a correr.
- `activate()` queda para un único llamador: `ResolveTenant`, dentro de la
  transacción de la petición (y para los helpers de los tests). Lanza una
  excepción si se llama fuera de una transacción. Una regla de
  arquitectura lo fija.
- Los seis cambios de plantel actuales pasan a `within()`.

### 4. Cola y consola

- El plantel en memoria (`CurrentTenant`) pasa de *singleton* a *scoped*.
  Laravel lo vacía entre peticiones y entre trabajos de la cola.
- Al empezar cada trabajo, un escuchador comprueba que la conexión no tiene
  plantel. Si lo tiene, lo limpia y lo reporta: indica un fallo en otro
  sitio (RLT-R03.1).
- Un trabajo que actúa sobre un plantel guarda su id al encolarse, y un
  middleware de trabajo lo ejecuta con `within()` (RLT-R03.2).
- Los comandos que recorren planteles usan `within()` en cada uno
  (RLT-R03.3).
- La cola despacha los trabajos tras el `COMMIT` (`after_commit`), para que
  un trabajo no lea datos que la petición aún no confirmó.

## Alternativas

| Alternativa | Por qué no |
|-------------|------------|
| **Mantener el ajuste por conexión y limpiarlo al terminar cada petición y trabajo** | No cumple RLT-R01.4: con un pool en modo transacción, dos sentencias de una petición pueden ir por conexiones distintas. Depende además de que la limpieza corra siempre: un `exit`, un error fatal o un trabajador que muere a medias dejan el plantel puesto. |
| **Fijar el plantel en cada transacción y envolver cada sentencia suelta en su propia transacción** (una conexión de Laravel a medida) | Cumple los requisitos, pero cuesta tres viajes más por sentencia (`BEGIN`, `set_config`, `COMMIT`), exige sustituir la clase de conexión del framework y cambia el comportamiento de cada consulta suelta. Es más código y más lento que una transacción por petición. |
| **Un rol de base de datos por plantel** (`SET ROLE`) | Miles de roles, y `SET ROLE` es también un ajuste de sesión: el mismo problema con más piezas. |
| **Funciones `SECURITY DEFINER` que reciban el plantel** | Descartadas en el ADR 0006 (S-03). |

## Consecuencias

- **Positivas:**
  - El plantel no puede sobrevivir a una transacción, por construcción de
    PostgreSQL y no por disciplina del código.
  - La aplicación es correcta con PgBouncer en modo transacción, con
    conexiones persistentes y con trabajadores de larga vida.
  - Cada petición de un plantel es atómica: un error de servidor no deja
    escrituras a medias.
  - Producción se comporta como los tests.
- **Negativas:**
  - **Transacciones más largas.** Duran lo que la petición, y los bloqueos
    (`FOR UPDATE`, el *advisory lock* del calendario, ADR 0007) se
    mantienen hasta el final. Las peticiones son cortas: no hay llamadas
    externas largas dentro de una petición de plantel. Si las hubiera
    (p. ej. la IA de la Fase 2), deberán ir a la cola.
  - **Con PgBouncer, una conexión real por petición en curso.** El pool se
    dimensiona por peticiones concurrentes de planteles, no por sentencias.
  - **Una sentencia que falla aborta la transacción entera** si no está
    dentro de un *savepoint*. Los repositorios que capturan errores de
    unicidad ya lo hacen dentro de `TransactionManager::run`, y los tests
    lo confirman porque ya corren dentro de una transacción.
  - **Detrás de un pool, PDO emula las consultas preparadas**
    (`DB_EMULATE_PREPARES=true`) y PgBouncer no las guarda
    (`max_prepared_statements = 0`). Con `max_prepared_statements`, el pool
    reutiliza una consulta preparada antes de una migración y PostgreSQL
    responde «cached plan must not change result type» hasta que el pool
    renueve sus conexiones (SPEC-015, PR B).
  - **Un coste fijo por petición de plantel** (`BEGIN`, `set_config`,
    `COMMIT`). SPEC-015 lo limita a 5 ms en el p95 (RLT-NF03).
  - **Las respuestas en streaming** que lean la base de datos después de
    devolverse la respuesta lo harían fuera de la transacción, sin plantel:
    no verían nada. Hoy no hay ninguna. Un test lo vigila.
