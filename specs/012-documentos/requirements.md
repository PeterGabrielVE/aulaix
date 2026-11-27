---
spec: 012-documentos
prefijo: DOC
estado: aprobada
fecha: 2026-11-27
aprobada: 2026-11-27
backlog: F1-32, F1-33, F1-34, F1-35, F1-36 (alcance de documentos del plantel y QR; formatos oficiales MPPE/CDCE diferidos a F0-02)
módulo: Documents (nuevo)
depende-de: 005-auditoria, 006-institucion (datos, logo y firmas), 007-anio-escolar (lapsos cerrados), 008-estructura-academica (pensum, grados coordinados), 009-estudiantes (ficha, retiros), 010-inscripcion (inscripciones, prosecución, egreso), 011-evaluacion (notas, literales, informes), 013-layout-menu (menú), 016-revision-pendientes (definitiva tras revisión, pendientes, egreso)
la-usan: portal del representante (F2-07)
---

# Requisitos — Documentos del plantel y verificación QR

## Contexto

Con el calendario escolar (SPEC-007), la estructura académica (SPEC-008), el
registro de estudiantes y retiros (SPEC-009), las inscripciones y prosecución
(SPEC-010) y las calificaciones, revisiones y pendientes (SPEC-011 y SPEC-016)
en marcha, los planteles necesitan emitir en PDF sus documentos cotidianos con
identidad institucional (logo y firmas de SPEC-006) y con un mecanismo público
de verificación mediante código QR que evite falsificaciones (backlog
`F1-32` – `F1-36`).

Los documentos oficiales que se consignan ante el MPPE / CDCE (Certificación de
Calificaciones `RR-DEA-03-03` y Resumen Final de Rendimiento Estudiantil)
dependen de validar las plantillas vigentes con un plantel piloto o CDCE
(`F0-02`) y quedan fuera de esta spec para no frenar la operación diaria del
plantel.

## Alcance

- **Incluye:**
  - **Boletas de calificaciones / informes por lapso** (Educación Inicial,
    Primaria y Media General), únicamente de lapsos cerrados;
  - **Boleta final del año escolar** (Educación Inicial, Primaria y Media
    General), una vez cerrado el tercer lapso o el año escolar, integrando
    pruebas de revisión y materias pendientes (SPEC-016) y el resultado de
    prosecución (SPEC-010);
  - **Emisión en lote de boletas por sección**, en un único archivo
    descargable, con registro individual y código QR propio para cada boleta;
  - **Constancias del plantel:**
    - constancia de estudio (inscripción vigente en el año activo);
    - constancia de inscripción (año activo o histórico);
    - constancia de retiro (estudiantes con retiro registrado en SPEC-009);
    - constancia de buena conducta (texto estándar del plantel);
    - constancia de egreso o prosecución (según resultado de SPEC-010 y
      SPEC-016);
  - **Encabezado institucional, logo y firmas** desde SPEC-006, con línea de
    firma física en blanco y aviso informativo si falta firma o código DEA;
  - **Inmutabilidad, historial, re-descarga y anulación** con motivo
    obligatorio;
  - **Verificación pública por código QR** (`F1-33`, `F1-36`) con códigos no
    secuenciales, límite de peticiones y exposición mínima de datos personales
    de menores;
  - **Permiso `emitir-documentos`**, alcance por grados para Coordinadores,
    entrada en el menú, bitácora y aislamiento por plantel.
- **Excluye:**
  - **Formatos oficiales ante el MPPE / CDCE** (Certificación de Calificaciones
    `RR-DEA-03-03`, Resumen Final de Rendimiento Estudiantil `RR-DEA-04-03` /
    `RR-DEA-05-03`, título de bachiller y exportación al Sistema de Gestión
    Escolar / Guaicaipuro): se especificarán en una spec propia al cerrarse
    `F0-02`;
  - emisión de boletas provisionales o borradores de lapsos abiertos;
  - descarga directa por parte del Representante o Estudiante (va en el Portal
    del Representante, `F2-07`);
  - emisión por parte del Docente guía (salvo que reciba `emitir-documentos`
    persona a persona);
  - asistencia en las boletas (aún no existe el módulo `Attendance`, `F2-03`);
  - editor visual de plantillas PDF por plantel;
  - envío automático de boletas por correo o WhatsApp (`Notifications`,
    `F2-05` / `F6-06`).

## Decisiones de producto incorporadas (2026-11-27)

| Tema | Decisión |
|------|----------|
| División frente a `F0-02` | SPEC-012 cubre los documentos propios del plantel y la verificación QR. Los formatos oficiales MPPE/CDCE quedan para una spec posterior al completar `F0-02`. |
| Catálogo de documentos | Boleta por lapso, boleta final del año, emisión en lote por sección, y constancias de estudio, inscripción, retiro, buena conducta y egreso/prosecución. |
| Estado del lapso para boletas | Solo de lapsos cerrados (y boleta final tras cerrar el 3.er lapso o el año), para que lo emitido sea firme. |
| Logo, DEA y firmas faltantes | No bloquean la emisión: si falta la firma digital se imprime línea de firma física en blanco y se muestra un aviso informativo en pantalla. |
| Permisos y alcance | Nuevo permiso `emitir-documentos`, asignado por defecto a Administrador, Director y Coordinador (este último acotado a sus grados coordinados), y asignable persona a persona a otros miembros activos. |
| Privacidad del QR público | Muestra únicamente estado (`válido`, `anulado`, `vencido`), plantel (+ DEA si existe), tipo, año/lapso, fechas de emisión/vencimiento e iniciales del estudiante + últimos 4 dígitos de su cédula o cédula escolar. Nunca nombre completo ni calificaciones. |
| Vigencia y anulación | Las constancias vigentes en el tiempo (estudio, inscripción, buena conducta) vencen a los 90 días por defecto (configurable 1–365 días). Boletas, constancias de retiro y de egreso/prosecución no vencen. Todo documento puede anularse con motivo obligatorio y sin borrado físico. |
| Verificación QR con plantel inactivo | La página pública de verificación QR sigue respondiendo en solo lectura aunque el plantel esté desactivado, sin revelar el estado comercial ni el motivo de desactivación; emisión, descarga y anulación permanecen bloqueadas (RSP-R08). |
| Constancia de buena conducta | Usa el texto estándar del plantel y admite una observación opcional breve de texto plano (hasta 300 caracteres); si se deja vacía no se muestra. La observación nunca aparece en la página pública del QR. |

## Glosario

| Término | Significado |
|---------|-------------|
| Documento emitido | Registro inmutable y PDF generado para un estudiante concreto en una fecha dada, con código de verificación único. |
| Boleta por lapso | Reporte PDF del rendimiento de un estudiante en un lapso cerrado, adaptado al nivel (informe en Inicial, literal en Primaria, notas por materia en Media General). |
| Boleta final | Reporte PDF del cierre del año escolar con los tres lapsos, definitivas, revisiones, materias pendientes y resultado de prosecución cuando existan. |
| Emisión en lote | Generación simultánea de las boletas de todos los estudiantes elegibles de una sección en un único archivo descargable, conservando registro y QR individuales. |
| Código de verificación | Identificador opaco, único y no secuencial impreso en el documento (en QR y en texto legible) para consultar su autenticidad públicamente. |
| Vigencia | Plazo (en días) durante el cual una constancia de vigencia temporal se considera `válida` antes de pasar a estado `vencido`. |
| Anulación | Acción irreversible que invalida un documento emitido dejando constancia de fecha, autor y motivo, sin eliminarlo del historial. |
| Estudiante elegible | Estudiante con inscripción no anulada en la sección y período correspondientes (incluye estudiantes retirados después del cierre del lapso evaluado). |

## Requisitos

### DOC-R01 — Boletas por lapso (Inicial, Primaria y Media General)

**Historia:** Como personal autorizado del plantel, quiero emitir la boleta en
PDF de un lapso cerrado para un estudiante, para entregar a su familia un
reporte formal y verificable de su rendimiento.

**Criterios de aceptación (EARS):**

- **DOC-R01.1** — Cuando una persona autorizada emite la boleta de un lapso
  cerrado de un estudiante de Media General, el sistema deberá generar un PDF
  con el encabezado del plantel, los datos del estudiante (nombre completo,
  cédula o cédula escolar, año escolar, grado y sección), cada materia del
  pensum con su nota del lapso (o estado «incompleta» / «no aplica» según
  EVA-R03), la fecha de emisión, las líneas de firma y el código QR de
  verificación.
- **DOC-R01.2** — Cuando una persona autorizada emite la boleta de un lapso
  cerrado de un estudiante de Primaria, el sistema deberá generar un PDF con su
  literal del lapso (A – E) y su descripción (EVA-R04), o «incompleto» si el
  lapso se cerró por excepción sin literal.
- **DOC-R01.3** — Cuando una persona autorizada emite la boleta de un lapso
  cerrado de un estudiante de Educación Inicial, el sistema deberá generar un
  PDF con su informe descriptivo del lapso (EVA-R05), o «incompleto» si el
  lapso se cerró por excepción sin informe.
- **DOC-R01.4** — Mientras el lapso solicitado no esté cerrado (abierto o sin
  abrir), el sistema deberá rechazar la emisión de la boleta de ese lapso e
  indicar que solo se emiten boletas de lapsos cerrados.
- **DOC-R01.5** — Si el estudiante no tiene inscripción válida (no anulada) en el
  año escolar y sección correspondientes, entonces el sistema deberá rechazar
  la emisión de la boleta.
- **DOC-R01.6** — Cuando un estudiante fue retirado después del cierre de un
  lapso, el sistema deberá permitir emitir la boleta de los lapsos cerrados que
  cursó en el plantel.

### DOC-R02 — Boleta final del año escolar

**Historia:** Como personal autorizado del plantel, quiero emitir la boleta
final del año escolar de un estudiante, para consolidar los tres lapsos, sus
revisiones, materias pendientes y resultado de prosecución.

**Criterios de aceptación (EARS):**

- **DOC-R02.1** — Cuando una persona autorizada emite la boleta final de un
  estudiante de Media General con el tercer lapso cerrado, el sistema deberá
  incluir, por cada materia, las notas de los tres lapsos, la definitiva, la
  nota de revisión y la definitiva tras revisión cuando existan (RVP-R02), las
  materias pendientes cursadas en ese año con su estado y definitiva tras
  revisión (RVP-R06), y el resultado de prosecución cuando esté registrado
  (SPEC-010).
- **DOC-R02.2** — Cuando una persona autorizada emite la boleta final de un
  estudiante de Primaria con el tercer lapso cerrado, el sistema deberá incluir
  los literales de los tres lapsos, el literal final (EVA-R04.3) y el resultado
  de prosecución cuando esté registrado.
- **DOC-R02.3** — Cuando una persona autorizada emite la boleta final de un
  estudiante de Educación Inicial con el tercer lapso cerrado, el sistema
  deberá incluir los informes descriptivos de los tres lapsos y el resultado de
  prosecución cuando esté registrado.
- **DOC-R02.4** — Mientras el tercer lapso del año escolar no esté cerrado, el
  sistema deberá rechazar la emisión de la boleta final.

### DOC-R03 — Emisión en lote de boletas por sección

**Historia:** Como personal autorizado del plantel, quiero descargar en un solo
paso las boletas de toda una sección, para no emitirlas una por una al cierre
de cada lapso.

**Criterios de aceptación (EARS):**

- **DOC-R03.1** — Cuando una persona autorizada solicita la emisión en lote de
  boletas de un lapso cerrado (o de boleta final con el tercer lapso cerrado)
  para una sección, el sistema deberá generar un único archivo PDF consolidado
  con una boleta por estudiante elegible de la sección, ordenados
  alfabéticamente por apellidos y nombres.
- **DOC-R03.2** — Cuando se emite un lote de boletas, el sistema deberá
  registrar cada boleta por separado en el historial de documentos emitidos,
  con su propio código QR único e independiente.
- **DOC-R03.3** — Si la sección no tiene estudiantes elegibles para el período
  solicitado, entonces el sistema deberá rechazar la emisión del lote sin
  registrar documentos.
- **DOC-R03.4** — Si el lapso solicitado no está cerrado (o el tercer lapso no
  está cerrado en una boleta final), entonces el sistema deberá rechazar la
  emisión del lote.

### DOC-R04 — Constancias del plantel

**Historia:** Como personal autorizado del plantel, quiero emitir constancias
oficiales del plantel en PDF con QR verificable, para atender rápidamente las
solicitudes de las familias y terceros.

**Criterios de aceptación (EARS):**

- **DOC-R04.1** — Cuando una persona autorizada emite una **constancia de
  estudio** para un estudiante con inscripción vigente en el año escolar
  activo, el sistema deberá generar un PDF con los datos del plantel, nombre
  completo y cédula (o cédula escolar) del estudiante, año escolar activo,
  grado y sección actuales, fecha de emisión, fecha de vencimiento (90 días por
  defecto, configurable entre 1 y 365 días), firmas y código QR. Si el
  estudiante no tiene inscripción vigente en el año activo, entonces el sistema
  deberá rechazarla.
- **DOC-R04.2** — Cuando una persona autorizada emite una **constancia de
  inscripción** para un estudiante con inscripción no anulada en un año escolar
  del plantel, el sistema deberá generar un PDF con año escolar, grado,
  sección, fecha de inscripción, fecha de emisión, fecha de vencimiento (90
  días por defecto, configurable entre 1 y 365 días), firmas y código QR. Si no
  existe inscripción válida en ese año, entonces el sistema deberá rechazarla.
- **DOC-R04.3** — Cuando una persona autorizada emite una **constancia de
  retiro** para un estudiante con un retiro registrado en el plantel
  (SPEC-009), el sistema deberá generar un PDF sin fecha de vencimiento con la
  fecha del retiro, último año escolar, grado y sección cursados, motivo del
  retiro si consta, fecha de emisión, firmas y código QR. Si el estudiante no
  tiene retiro registrado en el plantel, entonces el sistema deberá rechazarla.
- **DOC-R04.4** — Cuando una persona autorizada emite una **constancia de buena
  conducta** para un estudiante que tiene o tuvo al menos una inscripción no
  anulada en el plantel, el sistema deberá generar un PDF con el texto estándar
  del plantel, una observación opcional en texto plano (hasta 300 caracteres;
  omitida si queda vacía), el último grado y año escolar cursados, fecha de
  emisión, fecha de vencimiento (90 días por defecto, configurable entre 1 y
  365 días), firmas y código QR. Si el estudiante nunca tuvo una inscripción
  válida en el plantel, entonces el sistema deberá rechazarla.
- **DOC-R04.5** — Cuando una persona autorizada emite una **constancia de
  prosecución o egreso** para un estudiante con resultado de prosecución o
  egreso registrado en un año escolar (SPEC-010, SPEC-016), el sistema deberá
  generar un PDF sin fecha de vencimiento con el grado, año escolar y resultado
  obtenido (`promovido`, `promovido con materias pendientes`, `repite` o
  `egresado`), fecha de emisión, firmas y código QR. Si el estudiante no tiene
  resultado registrado en ese año —o si se solicita constancia de egreso
  estando «pendiente de egresar» con materias pendientes sin aprobar
  (RVP-R05.5)—, entonces el sistema deberá rechazarla.
- **DOC-R04.6** — Si se indica una vigencia menor que 1 día o mayor que 365
  días para una constancia con vencimiento, o si la observación opcional de una
  constancia de buena conducta supera los 300 caracteres, entonces el sistema
  deberá rechazar la emisión.

### DOC-R05 — Encabezado institucional, firmas e inmutabilidad

**Historia:** Como Administrador o Director, quiero que cada documento salga
con la identidad de mi plantel y que lo emitido no cambie retroactivamente,
para preservar la integridad de lo entregado.

**Criterios de aceptación (EARS):**

- **DOC-R05.1** — El sistema deberá incluir en todo documento emitido el nombre
  del plantel y, donde estén configurados en SPEC-006, su código DEA, RIF,
  ubicación, dirección, teléfono, correo y logo (INS-R04.6).
- **DOC-R05.2** — Donde el plantel tenga configurada la firma del Director (y
  la del Secretario/a, si existe) en SPEC-006, el sistema deberá incluir en el
  PDF la imagen de la firma junto con el nombre y cargo configurados
  (INS-R04.7).
- **DOC-R05.3** — Cuando al plantel le falte la imagen de firma del Director (o
  del Secretario/a) o el código DEA, el sistema deberá permitir emitir el
  documento imprimiendo la línea de firma física en blanco y mostrar en la
  pantalla de emisión un aviso indicando qué datos institucionales faltan.
- **DOC-R05.4** — Una vez emitido un documento, el sistema deberá conservar su
  PDF y sus metadatos de emisión de forma inmutable: modificaciones posteriores
  de notas (EVA-R06.3), revisiones (RVP-R01), datos del estudiante o
  logo/firmas del plantel no deberán alterar los documentos ya emitidos.
- **DOC-R05.5** — El sistema deberá mostrar el historial de documentos emitidos
  (por estudiante y en la vista general de Documentos, con filtros por tipo,
  año escolar y estado: `válido`, `vencido` o `anulado`) y permitir re-descargar
  el PDF exacto de cualquier documento no anulado.

### DOC-R06 — Anulación de documentos

**Historia:** Como personal autorizado del plantel, quiero anular un documento
emitido con error indicando el motivo, para que deje de ser válido al verificar
su QR sin borrar el rastro histórico.

**Criterios de aceptación (EARS):**

- **DOC-R06.1** — Cuando una persona autorizada anula un documento emitido con
  un motivo de entre 5 y 500 caracteres, el sistema deberá cambiar su estado a
  `anulado`, registrar fecha, autor y motivo, y conservarlo en el historial.
- **DOC-R06.2** — Si el motivo está vacío, tiene menos de 5 o más de 500
  caracteres, o si el documento ya estaba anulado, entonces el sistema deberá
  rechazar la anulación.
- **DOC-R06.3** — Mientras un documento esté anulado, el sistema deberá impedir
  su re-descarga y mostrar estado `anulado` en el historial y en la
  verificación pública por QR.

### DOC-R07 — Verificación pública por código QR

**Historia:** Como receptor de una boleta o constancia (otro plantel, organismo
o empresa), quiero escanear el código QR del documento para comprobar si fue
emitido por el plantel y si sigue vigente, sin exponer datos sensibles del
menor.

**Criterios de aceptación (EARS):**

- **DOC-R07.1** — El sistema deberá asignar a cada documento emitido un código
  de verificación único, opaco y no secuencial, e imprimirlo en el PDF como
  código QR (enlazando a la dirección pública de verificación del plantel) y
  como código legible.
- **DOC-R07.2** — Cuando cualquier persona sin iniciar sesión consulta la
  página pública de verificación con un código emitido por el plantel del
  subdominio, el sistema deberá mostrar únicamente:
  - estado del documento (`válido`, `anulado` con fecha de anulación, o
    `vencido` con fecha de vencimiento);
  - nombre del plantel y código DEA (si existe);
  - tipo de documento y período de referencia (año escolar y lapso, si aplica);
  - fecha y hora de emisión (y fecha de vencimiento si la tiene);
  - iniciales del estudiante y últimos 4 dígitos de su cédula o cédula escolar.
- **DOC-R07.3** — El sistema no deberá mostrar en la página pública de
  verificación el nombre completo del estudiante, su cédula completa, sus
  notas, literales, informes descriptivos, datos de representantes, la
  observación opcional de buena conducta ni el motivo interno de anulación.
- **DOC-R07.4** — Si el código consultado no existe o pertenece a otro plantel,
  entonces el sistema deberá responder con el mismo mensaje neutral («Documento
  no encontrado») sin revelar si el código existe en otro plantel.
- **DOC-R07.5** — Mientras el plantel esté inactivo (SPEC-014, RSP-R08), el
  sistema deberá mantener disponible en solo lectura la página pública de
  verificación por QR de sus documentos emitidos, sin revelar que el plantel
  está inactivo ni el motivo de desactivación, y manteniendo bloqueadas la
  emisión, la descarga y la anulación.

### DOC-R08 — Permisos, alcance y navegación

**Historia:** Como Administrador o Director, quiero controlar quién puede
emitir y anular documentos y limitar a cada Coordinador a sus grados, para
evitar emisiones indebidas.

**Criterios de aceptación (EARS):**

- **DOC-R08.1** — El sistema deberá definir el permiso `emitir-documentos`,
  incluirlo por defecto en los roles base `Administrador`, `Director` y
  `Coordinador` (también en los planteles existentes) y permitir asignarlo
  persona a persona a otros miembros activos (como `gestionar-inscripciones`,
  MAT-R11.3).
- **DOC-R08.2** — El sistema deberá permitir emitir, consultar historial,
  re-descargar y anular documentos únicamente a quien tenga el permiso
  `emitir-documentos`. Si alguien sin el permiso lo intenta, entonces el
  sistema deberá responder 403.
- **DOC-R08.3** — Mientras quien actúa tenga el rol `Coordinador` (y no sea
  `Administrador` ni `Director`), el sistema deberá limitar la emisión, la
  emisión en lote, el historial, la re-descarga y la anulación únicamente a
  estudiantes, secciones y documentos de los grados que coordina (EAC-R06). Si
  intenta operar sobre un estudiante, sección o documento fuera de sus grados,
  entonces el sistema deberá responder como si no existiera.
- **DOC-R08.4** — El sistema deberá mostrar la entrada «Documentos» en el menú
  lateral del plantel (SPEC-013, grupo «Estudiantes») a quien tenga
  `emitir-documentos`, y ofrecer accesos directos de emisión en la ficha del
  estudiante (SPEC-009) y en la sección.

### DOC-R09 — Bitácora

- **DOC-R09.1** — El sistema deberá registrar en la bitácora del plantel
  (SPEC-005):
  - cada documento individual emitido (ID del documento, tipo, estudiante,
    período y autor);
  - cada emisión en lote de boletas (sección, tipo, período, cantidad de
    boletas emitidas y autor);
  - cada anulación (ID del documento, autor y motivo).
- **DOC-R09.2** — El sistema no deberá guardar en la bitácora ni en los logs de
  aplicación el contenido binario del PDF, las imágenes de firmas ni el código
  completo de verificación (S-07).

### DOC-R10 — Aislamiento por plantel

- **DOC-R10.1** — El sistema deberá almacenar y consultar los documentos
  emitidos aislados por plantel mediante RLS en la base de datos y separación
  de archivos en almacenamiento privado (S-01, INS-R07.2).
- **DOC-R10.2** — El sistema deberá tomar el plantel únicamente del subdominio
  de la petición (S-02), tanto en las rutas autenticadas de gestión como en la
  ruta pública de verificación por QR.
- **DOC-R10.3** — Si se intenta emitir, consultar, re-descargar o anular
  usando un estudiante, una sección o un documento de otro plantel, entonces el
  sistema deberá responder como si no existiera.

## Requisitos no funcionales

- **DOC-NF01** (seguridad) — Toda acción de emisión, historial, descarga y
  anulación exige `emitir-documentos` en la ruta y en el caso de uso, además
  de la comprobación de alcance de grados para Coordinadores (S-04).
- **DOC-NF02** (seguridad) — Cada código de verificación deberá generarse con
  al menos 128 bits de entropía criptográfica, ser no secuencial y no derivarse
  del identificador interno del documento ni del estudiante.
- **DOC-NF03** (seguridad) — La ruta pública de verificación por QR deberá
  estar limitada por tasa por plantel e IP (máximo 30 peticiones por minuto por
  IP y plantel; S-06).
- **DOC-NF04** (seguridad) — Los archivos PDF generados se guardan en
  almacenamiento privado del plantel (fuera del directorio público) y todo
  texto proveniente de usuarios (nombres, descripciones de literales, informes
  descriptivos, motivos) se escapa al renderizar el PDF para impedir inyección
  de HTML o carga de recursos externos (SSRF).
- **DOC-NF05** (rendimiento) — La emisión de un documento individual responde
  en menos de 2 s (p95); la verificación pública por QR responde en menos de
  200 ms (p95); un lote de hasta 40 boletas de una sección se genera en menos
  de 15 s (p95).
- **DOC-NF06** (usabilidad y accesibilidad) — Las pantallas de documentos y la
  página pública de verificación por QR cumplen WCAG 2.1 nivel AA, funcionan en
  360 px de ancho (uso móvil tras escanear un QR) y emplean la terminología
  «plantel» (IDG-NF08).

## Cambios en otras specs

- **SPEC-001** (`TEN-R03.3`): documentar que la ruta pública `GET` de
  verificación por QR de SPEC-012 (`DOC-R07.5`) es la única lectura permitida
  sobre el subdominio de un plantel inactivo, en transacción de solo lectura,
  sin revelar su estado ni permitir escrituras ni inicio de sesión.
- **SPEC-002** (`IAM-R11.1`): incorporar `emitir-documentos` en `Administrador`,
  `Director` y `Coordinador`.
- **SPEC-013** (`NAV-R03`): incorporar la entrada «Documentos» en el grupo
  «Estudiantes» para quien tenga `emitir-documentos`.
- **Roadmap** (`specs/roadmap.md`): reflejar que SPEC-012 cubre documentos del
  plantel y verificación QR, mientras que los formatos oficiales MPPE/CDCE
  (Certificación de Calificaciones y Resumen Final) quedan pendientes de
  `F0-02`.

## Fuera de alcance y preguntas abiertas

Ninguna pregunta abierta: todas están resueltas en [Decisiones de producto
incorporadas](#decisiones-de-producto-incorporadas-2026-11-27).

Fuera de alcance en SPEC-012:
- Certificación de Calificaciones oficial MPPE (`RR-DEA-03-03`), Resumen Final
  de Rendimiento Estudiantil oficial MPPE (`RR-DEA-04-03` / `RR-DEA-05-03`),
  título de bachiller y exportación a Guaicaipuro (bloqueados por `F0-02`);
- boletas provisionales de lapsos abiertos;
- descarga directa por Representantes o Estudiantes (Portal del Representante,
  `F2-07`);
- asistencia en boletas (`Attendance`, `F2-03`);
- editor visual de plantillas PDF y envío automático por correo/WhatsApp.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| DOC-R01.1 | |
| DOC-R01.2 | |
| DOC-R01.3 | |
| DOC-R01.4 | |
| DOC-R01.5 | |
| DOC-R01.6 | |
| DOC-R02.1 | |
| DOC-R02.2 | |
| DOC-R02.3 | |
| DOC-R02.4 | |
| DOC-R03.1 | |
| DOC-R03.2 | |
| DOC-R03.3 | |
| DOC-R03.4 | |
| DOC-R04.1 | |
| DOC-R04.2 | |
| DOC-R04.3 | |
| DOC-R04.4 | |
| DOC-R04.5 | |
| DOC-R04.6 | |
| DOC-R05.1 | |
| DOC-R05.2 | |
| DOC-R05.3 | |
| DOC-R05.4 | |
| DOC-R05.5 | |
| DOC-R06.1 | |
| DOC-R06.2 | |
| DOC-R06.3 | |
| DOC-R07.1 | |
| DOC-R07.2 | |
| DOC-R07.3 | |
| DOC-R07.4 | |
| DOC-R07.5 | |
| DOC-R08.1 | |
| DOC-R08.2 | |
| DOC-R08.3 | |
| DOC-R08.4 | |
| DOC-R09.1 | |
| DOC-R09.2 | |
| DOC-R10.1 | |
| DOC-R10.2 | |
| DOC-R10.3 | |
| DOC-NF01 | |
| DOC-NF02 | |
| DOC-NF03 | |
| DOC-NF04 | |
| DOC-NF05 | |
| DOC-NF06 | |
