---
spec: 013-layout-menu
prefijo: NAV
estado: aprobada
fecha: 2026-10-23
aprobada: 2026-10-23
backlog: F1-11, F1-12
módulo: app/ (frontend y adaptadores de entrada); lee consultas de IdentityAccess, Tenancy, AcademicCalendar, AcademicStructure, Students y Audit
depende-de: 002-identidad-acceso (IAM-R12), 004-identidad-global, 005-auditoria, 006-institucion, 007-anio-escolar, 008-estructura-academica, 009-estudiantes
---

# Requisitos — Layout, menú e inicio por rol

## Contexto

Cada spec ha añadido su entrada a la barra superior. Hoy hay diez entradas
planas, una detrás de otra, y vendrán más (notas, asistencia, pagos). Las
pantallas de inicio por rol (IAM-R12) solo saludan y ofrecen algún acceso
directo: no dicen a nadie qué tiene que hacer ni cómo está el plantel.

Esta spec ordena la navegación y convierte el inicio en el punto de partida
de cada rol:

- un layout con barra lateral, que crece con los módulos y funciona en el
  teléfono;
- un menú que depende de lo que cada persona puede hacer, no de una lista
  fija por rol, de modo que sirve con varios roles a la vez;
- un inicio con accesos directos, indicadores, pendientes y contenido
  personal, con los módulos que ya existen.

Backlog: F1-11 (layout y menú) y F1-12 (dashboards por rol).

## Alcance

- **Incluye:**
  - el layout de las pantallas de un plantel: barra lateral, barra superior
    y panel de navegación en pantallas estrechas;
  - la barra superior: plantel, año escolar y lapso, persona y rol, cambio
    de plantel;
  - el menú, agrupado y según permisos y vínculos;
  - el inicio de cada rol base: Administrador, Director, Coordinador,
    Docente, Representante y Estudiante;
  - los bloques de otros roles en el inicio de quien tiene varios;
  - la dependencia de los planteles públicos (SPEC-006) y el rol
    Representante al vincular a un miembro (SPEC-009), que alimentan los
    pendientes y el inicio;
  - aislamiento, privacidad, rendimiento y accesibilidad de todo lo
    anterior.
- **Excluye:**
  - qué rol decide el inicio y el aviso para quien no tiene rol: siguen
    siendo IAM-R12.1 a R12.4, que esta spec no cambia;
  - las pantallas centrales, sin plantel abierto (selector de plantel,
    cuenta global): conservan su layout (SPEC-004);
  - indicadores que dependen de módulos futuros: cupos ocupados e
    inscritos por sección (SPEC-010), notas y rendimiento (SPEC-011),
    asistencia y pagos (Fase 2);
  - «clases de hoy» del docente: necesita horarios, que no existen;
  - el contenido para el Estudiante: su cuenta todavía no existe (SPEC-009
    la deja fuera);
  - notificaciones y mensajes (F2-05, F2-06);
  - personalizar el inicio (mover u ocultar bloques), tema oscuro y
    colores propios de cada plantel.

## Decisiones de producto incorporadas (2026-10-23)

| Pregunta | Decisión |
|----------|----------|
| Forma del layout | Barra lateral fija en escritorio. En pantallas estrechas se convierte en un panel que se abre desde la barra superior. |
| De qué depende el menú | De los permisos y vínculos de la persona, no de su rol principal. Sirve igual con roles personalizados y con varios roles. |
| Qué muestra el inicio | Accesos directos, indicadores, pendientes y contenido personal. |
| Varios roles | El inicio del rol principal (IAM-R12.2), más los bloques personales de sus otros roles o vínculos que no se solapen. |
| Coordinador | Indicadores y pendientes limitados a los grados que coordina en el año activo. |
| Barra superior | Plantel (logo y nombre), año escolar y lapso, nombre y rol principal, y «Cambiar de plantel». |
| Administrador y Director | Comparten lo académico. El Administrador ve además lo operativo: invitaciones, configuración del plantel y bitácora reciente. |
| Accesibilidad | WCAG 2.1 nivel AA, verificada de forma automática con un navegador de pruebas (Pest con Playwright y axe). Se añade como dependencia de desarrollo. |
| Datos obligatorios del plantel | Son pendientes del Administrador: niveles, código DEA (lo exige todo documento oficial), firma del Director titular (constancias y boletines), RIF si el plantel es privado (cobros) y dependencia si es público (nacional, estadal o municipal). |
| Representante sin rol | Vincular una cuenta a un representante le da el rol Representante en el plantel, también si ya era miembro con otro rol. |

## Glosario

| Término | Significado |
|---------|-------------|
| Pantalla del plantel | Cualquier pantalla autenticada servida en el subdominio de un plantel. |
| Rol principal | El rol base de mayor prioridad de la persona en el plantel (IAM-R12.2). |
| Entrada | Un enlace del menú a una pantalla. |
| Grupo | Conjunto de entradas del menú con un título (Académico, Estudiantes, Plantel). |
| Bloque | Una parte del inicio: accesos directos, indicadores, pendientes o contenido personal. |
| Indicador | Una cifra del plantel o de los grados de un coordinador, con su enlace. |
| Pendiente | Una situación que alguien debe resolver, con cuántos casos hay y un enlace a donde se resuelve. |
| Grados coordinados | Los grados que un coordinador tiene a su cargo en el año activo: los de los niveles completos que coordina y los grados concretos que se le asignaron (EAC-R06). |
| Persona no activa | Quien tiene una asignación en el plantel pero ya no es miembro activo (EAC-R08.5). |

## Requisitos

### NAV-R01 — Layout de las pantallas del plantel

**Historia:** Como miembro de un plantel, quiero la misma estructura en
todas las pantallas, con el menú siempre a mano, para moverme sin
perderme, también desde el teléfono.

**Criterios de aceptación (EARS):**

- **NAV-R01.1** — El sistema deberá mostrar cada pantalla del plantel con
  una barra lateral de navegación, una barra superior y el contenido.
- **NAV-R01.2** — Mientras el ancho de la ventana es menor de 1024 px, el
  sistema deberá ocultar la barra lateral y ofrecer en la barra superior un
  botón que abre el menú como panel.
- **NAV-R01.3** — Cuando la persona elige una entrada en el panel, o lo
  cierra con su botón o con la tecla Escape, el sistema deberá cerrar el
  panel.
- **NAV-R01.4** — El sistema deberá marcar como actual la entrada de la
  pantalla abierta y de las que dependen de ella (p. ej. la ficha de un
  estudiante marca «Estudiantes»).
- **NAV-R01.5** — Cuando una acción termina con un mensaje de éxito, el
  sistema deberá mostrarlo sobre el contenido, en todas las pantallas del
  plantel.

### NAV-R02 — Barra superior

**Historia:** Como miembro de un plantel, quiero ver en todo momento en qué
plantel, año y lapso estoy trabajando y con qué rol, para no cargar datos en
el lugar equivocado.

**Criterios de aceptación (EARS):**

- **NAV-R02.1** — El sistema deberá mostrar en la barra superior el nombre
  del plantel y, donde el plantel tenga logo, el logo (INS-R04).
- **NAV-R02.2** — Mientras el plantel tiene un año escolar activo, el
  sistema deberá mostrar su nombre y el del lapso abierto (CAL), o «sin
  lapso abierto» si no hay ninguno.
- **NAV-R02.3** — Mientras el plantel no tiene un año escolar activo, el
  sistema deberá mostrar «Sin año escolar activo».
- **NAV-R02.4** — El sistema deberá mostrar el nombre de la persona y el de
  su rol principal; si no tiene rol, solo su nombre.
- **NAV-R02.5** — Donde la cuenta tenga membresía activa en más de un
  plantel, el sistema deberá ofrecer «Cambiar de plantel», que lleva al
  selector central sin pedir la contraseña (IDG-R03.7).
- **NAV-R02.6** — Si la cuenta solo tiene membresía activa en el plantel
  abierto, entonces el sistema no deberá ofrecer «Cambiar de plantel».
- **NAV-R02.7** — El sistema deberá ofrecer, desde el nombre de la persona,
  «Perfil» y «Cerrar sesión».
- **NAV-R02.8** — Cuando se activa o se cierra un año escolar, o se abre o
  se cierra un lapso, el sistema deberá mostrar el cambio en la barra
  superior desde la siguiente pantalla que abra cualquier miembro del
  plantel.

### NAV-R03 — Menú según permisos y vínculos

**Historia:** Como miembro de un plantel, quiero ver en el menú solo lo que
puedo usar, ordenado por áreas, aunque tenga varios roles.

El menú tiene estas entradas, en este orden:

| Grupo | Entrada | Se muestra a quien… |
|-------|---------|---------------------|
| — | Inicio | es miembro activo del plantel |
| Académico | Año escolar | tiene `gestionar-calendario` |
| Académico | Estructura | tiene `ver-estructura` |
| Académico | Mis asignaciones | tiene el rol Docente |
| Estudiantes | Estudiantes | tiene `ver-estudiantes` |
| Estudiantes | Traslados | tiene `gestionar-estudiantes` |
| Estudiantes | Correcciones | tiene `gestionar-estudiantes` |
| Estudiantes | Mis representados | es representante vinculado de un estudiante del plantel (EST-R08.2) |
| Plantel | Plantel | tiene `ver-plantel` |
| Plantel | Usuarios | tiene `gestionar-usuarios` |
| Plantel | Bitácora | tiene `ver-bitacora` |

**Criterios de aceptación (EARS):**

- **NAV-R03.1** — El sistema deberá mostrar cada entrada de la tabla solo a
  quien cumple su condición, sumando lo que le dan todos sus roles y
  vínculos.
- **NAV-R03.2** — El sistema deberá mostrar las entradas bajo el título de
  su grupo, en el orden de la tabla, y ocultar un grupo que no tenga
  entradas para esa persona.
- **NAV-R03.3** — El sistema deberá mostrar una entrada a una persona si y
  solo si la pantalla a la que lleva le permite el acceso: ninguna entrada
  lleva a un 403 y ninguna pantalla accesible queda sin su entrada.
- **NAV-R03.4** — Cuando cambian los roles, permisos o vínculos de una
  persona, el sistema deberá reflejarlo en su menú en la siguiente pantalla
  que abra.
- **NAV-R03.5** — Si una persona abre por su dirección una pantalla cuya
  entrada no ve, entonces el sistema deberá denegarlo como hasta ahora: el
  menú no sustituye a la autorización de la ruta y del caso de uso (S-04).

### NAV-R04 — Inicio: reglas comunes

**Historia:** Como miembro de un plantel, quiero que el inicio me diga qué
tengo que hacer y me lleve directo a ello.

**Criterios de aceptación (EARS):**

- **NAV-R04.1** — Cuando la persona abre su inicio (IAM-R12.1), el sistema
  deberá mostrar el saludo y, en este orden, los pendientes, los
  indicadores, el contenido personal y los accesos directos que le
  correspondan.
- **NAV-R04.2** — El sistema deberá incluir en el inicio solo los bloques
  cuya pantalla de origen la persona puede abrir (la misma condición que su
  entrada en el menú, NAV-R03).
- **NAV-R04.3** — El sistema deberá enlazar cada indicador y cada pendiente
  con la pantalla donde se consulta o se resuelve.
- **NAV-R04.4** — Mientras no hay pendientes que mostrar, el sistema deberá
  indicar «No hay pendientes» en lugar del bloque vacío.
- **NAV-R04.5** — El sistema deberá calcular indicadores y pendientes al
  abrir el inicio, con el estado del plantel en ese momento.
- **NAV-R04.6** — Donde la persona tenga el rol Docente y no sea su rol
  principal, el sistema deberá añadir a su inicio el bloque «Mis clases» del
  Docente (NAV-R07.1).
- **NAV-R04.7** — Donde la persona sea representante vinculado y ese no sea
  su rol principal, el sistema deberá añadir a su inicio el bloque «Mis
  representados» (NAV-R08.1).
- **NAV-R04.8** — El sistema no deberá repetir un bloque en el mismo
  inicio, aunque varios roles de la persona lo aporten.
- **NAV-R04.9** — El sistema deberá ofrecer como accesos directos las
  entradas del menú que la persona ve, salvo «Inicio», con una frase que
  explica para qué sirve cada una.

### NAV-R05 — Inicio de la dirección (Director y Administrador)

**Historia:** Como Director o Administrador, quiero ver de un vistazo cómo
está organizado el plantel y qué falta, para resolverlo antes de que afecte
a inscripciones y notas.

**Criterios de aceptación (EARS):**

- **NAV-R05.1** — El sistema deberá mostrar estos indicadores:
  - estudiantes activos del plantel;
  - secciones del año activo y la suma de sus cupos;
  - miembros activos con cada rol base (quien tiene dos roles cuenta en
    ambos);
  - año escolar activo y lapso abierto.
- **NAV-R05.2** — Mientras el plantel no tiene año escolar activo, el
  sistema deberá mostrar como pendiente «No hay año escolar activo»,
  indicando si hay un año planificado.
- **NAV-R05.3** — Mientras hay año activo, el sistema deberá mostrar como
  pendientes, con su número de casos:
  - grados ofrecidos por el plantel sin ninguna sección;
  - secciones sin docente guía;
  - materias del pensum sin docente en alguna sección (cada par sección y
    materia es un caso);
  - asignaciones de personas no activas (EAC-R08.5).
- **NAV-R05.4** — El sistema deberá mostrar como pendientes las
  solicitudes de traslado recibidas que esperan respuesta (EST-R07.4) y las
  solicitudes de corrección sin atender (EST-R08.4).
- **NAV-R05.5** — Donde el rol principal sea Administrador, el sistema
  deberá mostrar además, como pendiente, las invitaciones sin aceptar,
  separando las vencidas (IDG-R04).
- **NAV-R05.6** — Donde el rol principal sea Administrador, el sistema
  deberá mostrar como pendientes los datos obligatorios del plantel que
  falten, cada uno con lo que impide y un enlace a «Plantel»:
  - niveles que ofrece (INS-R03): impide organizar la estructura;
  - código DEA (INS-R01): impide emitir documentos oficiales;
  - firma del Director titular, con su nombre (INS-R04): impide emitir
    constancias y boletines;
  - RIF, si el plantel es privado (INS-R01, R02): impide cobrar;
  - dependencia, si el plantel es público (INS-R02.4).
- **NAV-R05.7** — Donde el rol principal sea Administrador, el sistema
  deberá mostrar las 10 entradas más recientes de la bitácora del plantel,
  con fecha, persona y acción, y un enlace a la bitácora completa.

### NAV-R06 — Inicio del Coordinador

**Historia:** Como Coordinador, quiero ver la organización y los pendientes
de los grados que coordino, sin el ruido del resto del plantel.

**Criterios de aceptación (EARS):**

- **NAV-R06.1** — El sistema deberá mostrar al Coordinador, solo para sus
  grados coordinados, las secciones y la suma de sus cupos, y el año activo
  y el lapso abierto.
- **NAV-R06.2** — El sistema deberá mostrar al Coordinador los pendientes
  de NAV-R05.3, salvo los grados sin secciones, limitados a sus grados
  coordinados.
- **NAV-R06.3** — Mientras el Coordinador no tiene grados coordinados en el
  año activo, el sistema deberá mostrarle un aviso en lugar de indicadores y
  pendientes.
- **NAV-R06.4** — Mientras el plantel no tiene año escolar activo, el
  sistema deberá mostrar al Coordinador «No hay año escolar activo» en lugar
  de indicadores y pendientes.

### NAV-R07 — Inicio del Docente

**Historia:** Como Docente, quiero ver al entrar qué materias dicto y qué
secciones guío este año.

**Criterios de aceptación (EARS):**

- **NAV-R07.1** — El sistema deberá mostrar el bloque «Mis clases»: las
  materias que dicta, cada una con su sección, y las secciones que guía, en
  el año activo (EAC-R08.2).
- **NAV-R07.2** — Mientras el Docente no tiene asignaciones en el año
  activo, el sistema deberá mostrar en «Mis clases» que aún no tiene
  asignaciones.
- **NAV-R07.3** — Mientras el plantel no tiene año escolar activo, el
  sistema deberá mostrar en «Mis clases» «No hay año escolar activo».

### NAV-R08 — Inicio del Representante

**Historia:** Como Representante, quiero ver a mis representados y el
estado de lo que he pedido al plantel.

**Criterios de aceptación (EARS):**

- **NAV-R08.1** — El sistema deberá mostrar el bloque «Mis representados»:
  cada estudiante del plantel al que está vinculado, con un enlace a su
  ficha básica (EST-R08.2).
- **NAV-R08.2** — El sistema deberá mostrar las solicitudes de corrección
  que el representante envió y siguen sin atender (EST-R08.4).
- **NAV-R08.3** — Mientras el representante no tiene representados en el
  plantel, el sistema deberá indicarlo en el bloque en lugar de la lista.

### NAV-R09 — Inicio del Estudiante

**Historia:** Como Estudiante, quiero saber qué puedo hacer en AulaX.

**Criterios de aceptación (EARS):**

- **NAV-R09.1** — Mientras no exista contenido para el Estudiante, el
  sistema deberá mostrarle en su inicio un aviso de que podrá consultar sus
  datos y notas más adelante.

### NAV-R10 — Aislamiento y privacidad

**Historia:** Como plantel, quiero que el inicio no muestre datos de otros
planteles ni lo que una persona no puede ver en su pantalla de origen.

**Criterios de aceptación (EARS):**

- **NAV-R10.1** — El sistema deberá calcular indicadores, pendientes,
  bitácora reciente y contenido personal solo con datos del plantel
  abierto (S-01, S-02).
- **NAV-R10.2** — Si la persona no puede abrir la pantalla de origen de un
  bloque, entonces el sistema no deberá enviar al navegador los datos de ese
  bloque, ni siquiera ocultos.
- **NAV-R10.3** — El sistema no deberá mostrar en el inicio datos de salud
  de los estudiantes ni nombres de estudiantes, salvo los representados de
  la propia persona (NAV-R08.1).

## Requisitos no funcionales

- **NAV-NF01** (seguridad) — El inicio y el menú solo leen. No hay rutas
  nuevas de escritura y cada dato sale de las consultas públicas de su
  módulo (A-06), con la misma autorización que su pantalla (S-04).
- **NAV-NF02** (rendimiento) — El inicio de la dirección de un plantel con
  2000 estudiantes, 60 secciones y 100 miembros carga en menos de 300 ms
  (p95).
- **NAV-NF03** (rendimiento) — Lo que el layout comparte en cada página
  (menú, año y lapso, rol principal, cambio de plantel) no añade más de 5
  consultas a la base de datos por petición.
- **NAV-NF04** (accesibilidad) — El layout y los inicios cumplen WCAG 2.1
  nivel AA. En particular:
  - todo se usa solo con el teclado, con el foco siempre visible;
  - un enlace «Saltar al contenido» es lo primero que recibe el foco;
  - hay regiones de navegación, cabecera y contenido principal;
  - la entrada actual se anuncia como tal;
  - el panel del menú retiene el foco mientras está abierto y lo devuelve
    al botón al cerrarse;
  - el texto tiene un contraste de al menos 4,5:1.

  Se verifica con axe en un navegador de pruebas, sobre el layout y el
  inicio de cada rol base, en escritorio y a 360 px: ninguna infracción de
  nivel A o AA.
- **NAV-NF05** (usabilidad) — El layout y los inicios funcionan en 360 px
  de ancho sin desplazamiento horizontal (F1-11) y usan la terminología
  «plantel» (IDG-NF08).

## Cambios en otras specs

Se aplican en el mismo PR que los implemente (P-04), cada uno con su test
en la matriz de su spec. Los criterios nuevos se definen en su spec dueña;
aquí solo se citan:

- **IAM-R12 (SPEC-002):** sin cambios. Esta spec define el contenido de las
  pantallas de inicio a las que IAM-R12 lleva.
- **EAC-R08.2 (SPEC-008) y EST-R08.2 (SPEC-009):** sus entradas «Mis
  asignaciones» y «Mis representados» pasan a la tabla de NAV-R03, con las
  mismas condiciones. Por NAV-R03.3, «Mis asignaciones» responde 403 a
  quien no tiene el rol Docente.
- **IDG-R03.7 (SPEC-004):** «Cambiar de plantel» se ofrece solo a quien
  tiene membresía activa en otro plantel (NAV-R02.5, R02.6).
- **SPEC-006, criterios nuevos de dependencia:**
  - INS-R02.4 — Mientras el plantel es público, el sistema deberá
    permitir al Administrador indicar su dependencia: `nacional`,
    `estadal` o `municipal`, y rechazar cualquier otro valor.
  - INS-R02.5 — Mientras el plantel es privado o subvencionado, el
    sistema no deberá guardar ninguna dependencia.

  Los planteles públicos existentes quedan sin dependencia, y NAV-R05.6 la
  pide como pendiente. El cambio queda en la bitácora como el resto de los
  datos del plantel (INS-R06).
- **SPEC-009, criterio nuevo:**
  - EST-R08.7 — Cuando el sistema vincula a un representante la cuenta
    de alguien que ya es miembro del plantel sin el rol Representante, el
    sistema deberá darle ese rol y registrarlo en la bitácora.

  Así nadie vinculado queda sin rol (IAM-R12.3) y su inicio puede ser el de
  Representante. EST-R08.1 ya lo hace cuando la cuenta llega por
  invitación.

## Notas para el diseño

Orientaciones acordadas el 2026-10-23. Las decide el diseño:

- **Consultas del inicio.** Los pendientes de estructura (secciones sin
  guía, materias sin docente) son críticos al empezar el año. Conviene
  reunirlos en una consulta por módulo, sin una consulta por sección.
- **Datos del layout.** Año, lapso, rol principal y número de planteles se
  resuelven juntos, una vez por petición (NAV-NF03). Año y lapso cambian
  pocas veces al año y pueden ir en caché, siempre que la caché se
  invalide cuando cambian (NAV-R02.8).
- **Una sola definición del menú.** El backend declara las entradas y su
  condición, y el menú se dibuja con lo que el backend envía. El test de
  NAV-R03.3 recorre esa definición y pide cada ruta con cada rol base,
  comprobando que la respuesta (200 o 403) coincide con la entrada.

## Fuera de alcance y preguntas abiertas

Ninguna pregunta abierta: las tres del borrador inicial están resueltas en
[Decisiones de producto
incorporadas](#decisiones-de-producto-incorporadas-2026-10-23).

**Fuera de alcance:**
- la «zona educativa» del plantel: sale de su estado (ubicación, INS-R01);
- que falten datos obligatorios bloquee la emisión de documentos: lo
  decidirá SPEC-012. Aquí solo se avisa.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| NAV-R01.1 | |
| NAV-R01.2 | |
| NAV-R01.3 | |
| NAV-R01.4 | `tests/Feature/Workspace/LayoutPropsTest.php` › «groups the menu, with links and the current screen marked»; `tests/Feature/Workspace/LayoutPropsTest.php` › «marks the entry of a screen that depends on it» |
| NAV-R01.5 | |
| NAV-R02.1 | |
| NAV-R02.2 | `tests/Feature/AcademicCalendar/Infrastructure/CalendarContextQueryTest.php` › «names the active year and its open term»; `tests/Feature/AcademicCalendar/Infrastructure/CalendarContextQueryTest.php` › «says when the active year has no open term»; `tests/Feature/Workspace/LayoutPropsTest.php` › «shows the active year, its open term, the person and their primary role» |
| NAV-R02.3 | `tests/Feature/AcademicCalendar/Infrastructure/CalendarContextQueryTest.php` › «says when there is no active year, and whether one is planned»; `tests/Unit/Workspace/Application/ShowLayoutTest.php` › «says there is no active year, and offers no switch to a member of one institution»; `tests/Feature/Workspace/LayoutPropsTest.php` › «says there is no active year, or no open term» |
| NAV-R02.4 | `tests/Feature/IdentityAccess/Infrastructure/MembershipContextQueryTest.php` › «tells the roles, the highest-priority one and the active institutions of an account»; `tests/Feature/IdentityAccess/Infrastructure/MembershipContextQueryTest.php` › «has no primary role for a member without base roles»; `tests/Feature/Workspace/LayoutPropsTest.php` › «shows the active year, its open term, the person and their primary role» |
| NAV-R02.5 | `tests/Feature/IdentityAccess/Infrastructure/MembershipContextQueryTest.php` › «takes the active institutions from the session, counted where all of them were visible»; `tests/Unit/Workspace/Application/ShowLayoutTest.php` › «shows the menu, the year and term, the primary role and the switch»; `tests/Feature/IdentityAccess/HandoffTest.php` › «the institution menu offers to switch institution»; `tests/Feature/IdentityAccess/Infrastructure/CacheHandoffTokensTest.php` › «carries how many institutions the account was active in (SPEC-013 NAV-R02.5)» |
| NAV-R02.6 | `tests/Feature/IdentityAccess/HandoffTest.php` › «a member of one institution is not offered to switch (SPEC-013 NAV-R02.6)»; `tests/Unit/Workspace/Application/ShowLayoutTest.php` › «says there is no active year, and offers no switch to a member of one institution» |
| NAV-R02.7 | |
| NAV-R02.8 | `tests/Feature/AcademicCalendar/Infrastructure/CalendarContextQueryTest.php` › «sees a year activated a moment ago, with one query»; `tests/Feature/Workspace/LayoutPropsTest.php` › «says there is no active year, or no open term» |
| NAV-R03.1 | `tests/Unit/Workspace/Domain/CapabilitiesTest.php` › «shows each entry to whoever meets its condition»; `tests/Unit/Workspace/Domain/CapabilitiesTest.php` › «shows the home entry to every member»; `tests/Unit/Workspace/Domain/NavigationTest.php` › «adds the entries of every role and link a person has»; `tests/Unit/Workspace/Domain/NavigationTest.php` › «shows a teacher its classes and what it may consult» |
| NAV-R03.2 | `tests/Unit/Workspace/Domain/CapabilitiesTest.php` › «knows the eleven entries of the menu, in order and by group»; `tests/Unit/Workspace/Domain/NavigationTest.php` › «groups the entries of a Director, without the ones of other roles»; `tests/Unit/Workspace/Domain/NavigationTest.php` › «hides the groups without entries for a guardian»; `tests/Unit/Workspace/Domain/NavigationTest.php` › «leaves only the home entry to a member without roles»; `tests/Feature/Workspace/LayoutPropsTest.php` › «groups the menu, with links and the current screen marked» |
| NAV-R03.3 | `tests/Feature/Workspace/NavigationRouteParityTest.php` › «an entry shows exactly when its screen opens»; `tests/Feature/Workspace/NavigationRouteParityTest.php` › «every entry has its presentation, and only those» |
| NAV-R03.4 | `tests/Feature/Workspace/LayoutPropsTest.php` › «says there is no active year, or no open term»; `tests/Feature/Workspace/LayoutPropsTest.php` › «no longer shares the menu flags it replaces» |
| NAV-R03.5 | `tests/Feature/Workspace/NavigationRouteParityTest.php` › «an entry shows exactly when its screen opens» |
| NAV-R04.1 | |
| NAV-R04.2 | |
| NAV-R04.3 | |
| NAV-R04.4 | |
| NAV-R04.5 | |
| NAV-R04.6 | |
| NAV-R04.7 | |
| NAV-R04.8 | |
| NAV-R04.9 | |
| NAV-R05.1 | |
| NAV-R05.2 | |
| NAV-R05.3 | |
| NAV-R05.4 | |
| NAV-R05.5 | |
| NAV-R05.6 | |
| NAV-R05.7 | |
| NAV-R06.1 | |
| NAV-R06.2 | |
| NAV-R06.3 | |
| NAV-R06.4 | |
| NAV-R07.1 | |
| NAV-R07.2 | |
| NAV-R07.3 | |
| NAV-R08.1 | |
| NAV-R08.2 | |
| NAV-R08.3 | |
| NAV-R09.1 | |
| NAV-R10.1 | |
| NAV-R10.2 | |
| NAV-R10.3 | |
| NAV-NF01 | |
| NAV-NF02 | |
| NAV-NF03 | `tests/Feature/Workspace/LayoutQueryCountTest.php` › «the layout adds five queries at most to a page» |
| NAV-NF04 | |
| NAV-NF05 | |
| INS-R02.4 (SPEC-006) | Ver la matriz de `specs/006-institucion/requirements.md` (`InstitutionDependencyTest`, `UpdateInstitutionTest`, `EloquentInstitutionRepositoryTest`, `InstitutionDependencyColumnTest`, `InstitutionSettingsTest`) |
| INS-R02.5 (SPEC-006) | Ver la matriz de `specs/006-institucion/requirements.md` (`InstitutionDependencyTest`, `UpdateInstitutionTest`, `InstitutionDependencyColumnTest`, `InstitutionSettingsTest`) |
| EST-R08.7 (SPEC-009) | Ver la matriz de `specs/009-estudiantes/requirements.md` (`GuardianAccountTest`, `GrantRoleTest`, `GuardianInvitationTest`) |
