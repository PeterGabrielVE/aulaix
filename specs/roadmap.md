# Roadmap de specs

Cruce entre el backlog de producto (Notion) y la arquitectura: qué módulo es
dueño de cada épica, qué spec la cubre y qué la bloquea. El backlog sigue
siendo la fuente de prioridades; este documento, la de dependencias técnicas.

## Mapa de contextos (módulos de `src/`)

| Módulo | Responsabilidad | Épicas del backlog |
|--------|-----------------|--------------------|
| `Shared` | Kernel: excepciones, eventos, reloj, transacciones | — |
| `Tenancy` | Instituciones, subdominios, RLS, configuración del plantel | Multi-tenant, Institución (F1-09, F1-10), alta automática de planteles (F4-04) |
| `IdentityAccess` | Cuentas, membresías, roles, sesión | Autenticación y roles (F1-05 – F1-07) |
| `Catalogs` | Geografía y materias del MPPE | F1-04 |
| `Audit` | Bitácora de cambios sensibles (escucha eventos de dominio de todos los módulos) | Auditoría (F1-08) |
| `AcademicCalendar` | Año escolar y lapsos, apertura y cierre | Año escolar (F1-13 – F1-15) |
| `AcademicStructure` | Niveles, grados, secciones, pensum, asignación docente | Estructura académica (F1-16 – F1-19) |
| `Students` | Estudiantes, representantes, retiros y traslados | Estudiantes (F1-20 – F1-24) |
| `Enrollment` | Inscripción, prosecución, cupos | Inscripción (F1-25 – F1-27) |
| `Evaluation` | Planes de evaluación, notas, definitivas, revisión, pendientes | Evaluación (F1-28 – F1-31), Revisión y pendientes (F2-01, F2-02) |
| `Documents` | Boletas, constancias, certificaciones, verificación QR | Documentos (F1-32 – F1-36) |
| `Attendance` | Asistencia de estudiantes | F2-03, F2-04 |
| `Notifications` | Correo, internas, WhatsApp, citaciones | F2-05, F2-06, F6-06 |
| `Ai` | Puerto de IA, seudonimización, cuotas, casos de uso de IA | IA base / docente / analítica (F2-08 – F2-14, F3-02 – F3-08) |
| `Billing` | Conceptos de cobro, pagos, conciliación, morosidad | Pagos (F2-15 – F2-18, F3-01 – F3-03) |
| `Workspace` | Layout, menú e inicio por rol (vista compuesta, sin tablas: ADR 0009) | F1-11, F1-12 |
| `Reporting` | Reportes y tableros (solo lectura, CQRS) | F3-09, F3-10, F8-01, F8-02 |
| `Platform` | Operadores de plataforma, superadministración, planes y suscripciones | SaaS (F4-05, F4-06) |

Fases 4 a 8 (offline, personal, bienestar, aula virtual, integraciones):
sus módulos se definen cuando se especifiquen.

**Vistas como módulos sin tablas (ADR 0009):** el portal del representante (F2-07), los dashboards
por rol (F1-12) y las apps móviles componen queries de varios módulos. Sus
reglas de composición viven en su dominio; no tienen tablas ni escrituras.

## Specs de la Fase 1, en orden de dependencias

| Spec | Backlog | Módulo | Depende de | Estado |
|------|---------|--------|------------|--------|
| 000 Fundaciones hexagonales | F0-06 (parte) | `Shared`, `Ai` | — | **implementada** |
| 003 Catálogos (refactor) | F1-04 | `Catalogs` | 000 | **implementada** |
| 001 Tenancy (refactor) | F1-01 – F1-03 | `Tenancy` | 000 | **implementada** |
| 002 Identidad y acceso (refactor) | F1-05, F1-06 | `IdentityAccess` | se implementa con 004 | **se fusiona con 004**: no tiene sentido refactorizar el modelo de cuentas por plantel para cambiarlo justo después |
| 005 Bitácora de auditoría | F1-08 | `Audit` | 001 | **implementada**. **Antes que 004 y que Evaluación**: identidad y notas nacen auditadas |
| 004 Identidad global | F1-07, revisa F1-06 | `IdentityAccess` | 001, 005, ADR 0004 | **implementada** (PR A, B y C). La eliminación efectiva (IDG-R08.12) espera la validación legal (D1) |
| 006 Institución y configuración | F1-09, F1-10 | `Tenancy` | 001 | **implementada**. Expone `InstitutionSettingsQuery` (tipo y niveles) para 008 e `InstitutionAssetsQuery` (logo y firmas) para 012. Escalas → 011; módulos por tipo → spec de cada módulo |
| 007 Año escolar y lapsos | F1-13 – F1-15 | `AcademicCalendar` | 001 | **implementada** (ADR 0007). Expone `TermEditabilityQuery` para 011 |
| 008 Estructura académica | F1-16 – F1-19 | `AcademicStructure` | 003, 006, 007 | **implementada** (PR A y B; resuelve el ERD, F0-07). Expone `TeachingQuery` (qué secciones y materias dicta cada docente) para 009, 010 y 011, y activa IDG-R08.4 |
| 009 Estudiantes y representantes | F1-20 – F1-24 | `Students` | 004 | **implementada** (PR A, B y C; ADR 0008). Expone la ficha y los representantes para 010, 011 y 012, y activa IDG-R08.3 |
| 010 Inscripción | F1-25 – F1-27 | `Enrollment` | 008, 009 | **implementada** (PR A – C; ADR 0012): inscripción con cupo y edad por grado, cambios de sección y de grado, anulación, prosecución por nivel, egreso, cierre del año con resultados, inscripción en lote y visibilidad de Docente y Coordinador por secciones. Quedan abiertas Q6 y Q7 (Educación Inicial) |
| 011 Evaluación | F1-28 – F1-31 | `Evaluation` | 005, 007, 008, 010 | **implementada** (PR A – C; ADR 0012): plan de actividades por lapso, notas 1–20 con NP y NA, nota del lapso y definitiva exactas, literales en Primaria, informes en Inicial, lapso cerrado, cierre del lapso con pendientes o por excepción, propuesta del resultado de prosecución y las reglas de SPEC-008 y SPEC-010 sobre notas |
| 012 Documentos | F1-32 – F1-36 | `Documents` | 006, 011 | **bloqueada por F0-02** (formatos oficiales del MPPE) |
| 013 Layout y menú por rol | F1-11, F1-12 | `Workspace` (nuevo, ADR 0009), `app/` | 004 – 009 | **implementada** (PR A – D; ADR 0009). Layout con barra lateral, menú por permisos, inicio por rol y tests de navegador con axe |
| 014 Superadministración de plataforma | F4-06 (y operación de IDG-R11) | `Platform` (nuevo) | 004, 005, ADR nuevo | **implementada** (PR A – D; ADR 0010). Panel en `admin.APP_DOMAIN` con identidad propia y TOTP, operadores en dos niveles, planteles con su uso y activación, consolidaciones desde el panel (sustituye `IDENTITY_CONSOLIDATION_OPERATORS`) y bitácora de plataforma. Ver [consideraciones](#014--superadministración-de-plataforma) |
| 015 RLS por transacción y cierre de la revisión de seguridad | (previa a producción) | `Tenancy` (transversal), `IdentityAccess`, `Students` | 001, 004, 009, ADR nuevo | **implementada** (PR A, B y C; ADR 0011): el plantel vive en la transacción, la suite `rls` pasa también por PgBouncer en modo transacción (CI), traspaso ligado al navegador, origen de los traslados protegido e invitaciones bloqueadas mientras el correo espera consolidación. Cierra los cuatro hallazgos de la [revisión de seguridad](#revisión-de-seguridad) |
| — Infraestructura | F1-37, F1-38 | DevOps | — | CD listo (`.github/workflows/cd.yml`); faltan el servidor y los respaldos (F1-38) |

## 014 — Superadministración de plataforma

**Tarea:** escribir la spec del superadministrador. El backlog la sitúa en
la Fase 4 (F4-06: «lista de tenants, uso, plan y estado»). Puede adelantarse
si la operación de soporte lo exige: el comando de consola de IDG-R11 es la
solución temporal.

**Alcance a especificar**

- Lista de planteles con su estado, plan y uso (F4-06).
- Resolución de consolidaciones dudosas (IDG-R11), con las mismas reglas y
  casos de uso que el comando (IDG-NF07).
- Gestión de operadores de plataforma (sustituye la lista de operadores
  autorizados de la configuración, IDG-R11.3).
- Activación y desactivación de planteles. El alta automática queda en F4-04.

**Restricciones que la spec debe respetar** (necesitan un ADR antes del diseño)

- **Identidad separada.** El superadministrador es un actor de plataforma,
  no un rol de plantel: no puede ser un rol de Spatie dentro de un *team*, y
  su acceso debe exigir MFA.
- **Sin `BYPASSRLS` (S-03).** El panel ve datos de la plataforma (planteles,
  planes, métricas agregadas) a través de vistas o funciones `SECURITY
  DEFINER` de superficie mínima, nunca saltándose la RLS de forma general.
- **Sin acceso por defecto a datos personales ni académicos** de los
  planteles. Si hiciera falta para soporte, el acceso es excepcional
  («break-glass»): con motivo, limitado en el tiempo, auditado y visible
  para el plantel afectado.
- **Todo queda en la bitácora** (SPEC-005), incluidas las consultas, no solo
  los cambios.
- **Dominio propio** (p. ej. `admin.APP_DOMAIN`, ya reservado en
  `RESERVED_SUBDOMAINS`), con su propia sesión, separada de la de los
  planteles.

## Bloqueos de descubrimiento

Una spec no puede aprobarse con reglas de negocio inventadas (P-05). Estas
tareas de la Fase 0 son prerrequisito de specs concretas:

| Tarea | Desbloquea |
|-------|------------|
| F0-03 Reglas de evaluación por nivel | 010 Inscripción (prosecución), 011 Evaluación |
| F0-02 Formatos oficiales del MPPE | 012 Documentos |
| F0-07 ERD inicial | 008 – 011 (el ERD se vuelve el «Datos» de cada `design.md`) |
| F0-04 Prototipos validados | Criterios de UX de 011 (carga tipo hoja de cálculo) y 010 (asistente) |

## Requisitos de seguridad que ya marca el backlog

Conviene llevarlos a la constitución o a las specs antes de llegar a ellos:

- **F2-09** — Seudonimización: ningún nombre ni cédula de menores sale hacia
  el proveedor de IA. Es un requisito de diseño del puerto `Ai` desde el
  principio, no un parche posterior.
- **F3-06** — Consultas en lenguaje natural: nunca SQL libre y siempre
  filtradas por institución. Encaja con S-01: la RLS sigue siendo el límite.
- **F1-33 / F1-36** — Verificación pública por QR: los códigos no deben ser
  secuenciales ni enumerables, y la página pública no debe mostrar datos
  personales más allá de los imprescindibles.
- **F8-08** — Auditoría de seguridad externa: el objetivo ASVS nivel 2 (S-13)
  prepara para ella.

## Revisión de seguridad

**2026-10-23**: `/security-review` de la rama de SPEC-004 (PR A – C), 006,
007, 008, 009 y 013, en cuatro áreas:
- identidad y sesión;
- plantel, archivos, bitácora y calendario;
- estudiantes y traslados;
- estructura académica y Workspace.

Resultado: **sin hallazgos de confianza alta o media**.

Quedan anotados, por debajo del umbral, para revisarlos antes de
producción. **Los cuatro se cierran en SPEC-015** (2026-10-27): el plantel
por transacción (ADR 0011), el traspaso ligado al navegador, el origen de
`transfer_requests` protegido y las invitaciones bloqueadas mientras el
correo espera consolidación.

- **RLS por conexión.** `RowLevelSecurity::setInstitution` fija el plantel
  con `set_config(..., false)`, que dura toda la conexión. Con conexiones
  persistentes o un pool en modo transacción (PgBouncer), el plantel podría
  pasar de una petición a otra. Antes de producción: fijarlo por
  transacción, o garantizar que no hay pool de transacciones. Necesita spec
  o ADR.
- **Login CSRF con el traspaso.** Un miembro de un plantel podría enviar a
  otra persona su propio enlace de traspaso (de un solo uso y válido 60 s)
  y dejarla dentro con la sesión del atacante. El impacto es bajo; se
  evitaría ligando el traspaso a la sesión central que lo pidió.
- **Política `UPDATE` de `transfer_requests` (ADR 0008).** La base de datos
  deja al plantel de destino cambiar `origin_institution_id` en su propia
  fila. Ningún código de la aplicación lo expone; se cerraría con un
  `WITH CHECK` más estricto.
- **Cuentas históricas sin consolidar con el mismo correo.** Cada una
  podría aceptar invitaciones a ese correo hasta que se consoliden
  (IDG-R09). Depende de datos antiguos, no de código nuevo.
