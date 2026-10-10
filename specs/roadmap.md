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
| `Reporting` | Reportes y tableros (solo lectura, CQRS) | F3-09, F3-10, F8-01, F8-02 |
| `Platform` | Operadores de plataforma, superadministración, planes y suscripciones | SaaS (F4-05, F4-06) |

Fases 4 a 8 (offline, personal, bienestar, aula virtual, integraciones):
sus módulos se definen cuando se especifiquen.

**Vistas, no módulos:** el portal del representante (F2-07), los dashboards
por rol (F1-12) y las apps móviles componen queries de varios módulos. No
tienen dominio propio.

## Specs de la Fase 1, en orden de dependencias

| Spec | Backlog | Módulo | Depende de | Estado |
|------|---------|--------|------------|--------|
| 000 Fundaciones hexagonales | F0-06 (parte) | `Shared`, `Ai` | — | implementando (9 de 10 tareas) |
| 003 Catálogos (refactor) | F1-04 | `Catalogs` | 000 | requisitos implementados |
| 001 Tenancy (refactor) | F1-01 – F1-03 | `Tenancy` | 000 | requisitos implementados |
| 002 Identidad y acceso (refactor) | F1-05, F1-06 | `IdentityAccess` | se implementa con 004 | **se fusiona con 004**: no tiene sentido refactorizar el modelo de cuentas por plantel para cambiarlo justo después |
| 005 Bitácora de auditoría | F1-08 | `Audit` | 000 | por escribir. **Antes que 004 y que Evaluación**: identidad y notas nacen auditadas |
| 004 Identidad global | F1-07, revisa F1-06 | `IdentityAccess` | 001, 005, ADR 0004 | requisitos v3 **aprobados** (Q1–Q8 resueltas). La eliminación efectiva depende de la validación legal (D1) |
| 006 Institución y configuración | F1-09, F1-10 | `Tenancy` | 001 | por escribir |
| 007 Año escolar y lapsos | F1-13 – F1-15 | `AcademicCalendar` | 001 | por escribir. **Buen siguiente candidato**: reglas claras y pequeñas |
| 008 Estructura académica | F1-16 – F1-19 | `AcademicStructure` | 003, 007 | por escribir; necesita el ERD (F0-07) |
| 009 Estudiantes y representantes | F1-20 – F1-24 | `Students` | 004 | por escribir; formato de cédula escolar por confirmar |
| 010 Inscripción | F1-25 – F1-27 | `Enrollment` | 008, 009 | por escribir; **bloqueada por F0-03** (reglas de prosecución) |
| 011 Evaluación | F1-28 – F1-31 | `Evaluation` | 005, 007, 008, 010 | **bloqueada por F0-03** (escalas, redondeo, literales) |
| 012 Documentos | F1-32 – F1-36 | `Documents` | 006, 011 | **bloqueada por F0-02** (formatos oficiales del MPPE) |
| 013 Layout y menú por rol | F1-11, F1-12 | `app/` (frontend) | 004 | por escribir; en paralelo |
| 014 Superadministración de plataforma | F4-06 (y operación de IDG-R11) | `Platform` (nuevo) | 004, 005, ADR nuevo | por escribir. Hasta que exista, soporte opera con el comando de consola de IDG-R11. Ver [consideraciones](#014--superadministración-de-plataforma) |
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
