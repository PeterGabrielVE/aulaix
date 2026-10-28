---
adr: 0012
título: Reacciones entre módulos dentro de la misma transacción, por puntos de extensión
estado: aceptado
fecha: 2026-10-27
spec: 010-inscripcion
---

# ADR 0012 — Reacciones entre módulos dentro de la misma transacción, por puntos de extensión

## Contexto

La inscripción (SPEC-010, módulo `Enrollment`) depende de Students,
AcademicStructure y AcademicCalendar. Pero varias reglas suyas tienen que
cumplirse **dentro de una escritura de esos módulos**, en la misma
transacción:

- retirar o trasladar a un estudiante (Students) termina su inscripción,
  y si no puede terminarla, el retiro se deshace (MAT-R05.5);
- no se cierra el año escolar (AcademicCalendar) con estudiantes sin
  resultado (MAT-R16.2);
- no se quita una sección con inscripciones ni se baja su cupo por debajo
  de los inscritos (AcademicStructure, MAT-R10);
- Docente y Coordinador ven en Students solo a los estudiantes de sus
  secciones (MAT-R08).

Si Students, AcademicStructure o AcademicCalendar llamaran a Enrollment, la
dependencia sería circular: Enrollment ya los usa a ellos (A-06).

El `EventBus` (SPEC-000) publica **después del commit** (`afterCommit`): sirve
para efectos secundarios, como un correo o el vínculo de la cuenta del
representante (EST-R08.1), pero no para una regla que debe poder impedir la
escritura o fallar con ella.

SPEC-008 ya resolvió este caso para la estructura con `RemovalGuard`: una
interfaz en la capa Application del módulo que escribe, bajo una etiqueta
del contenedor, que implementan los módulos que vienen después. Hoy nadie la
implementa.

## Decisión

Las reacciones que forman parte de la misma regla de negocio se hacen con
**puntos de extensión síncronos**:

- **Quién lo declara.** El módulo que escribe declara en su capa
  Application una interfaz y una etiqueta del contenedor (`TAG`). Es el
  patrón de `RemovalGuard`.
- **Cuándo se llama.** Su caso de uso recorre lo registrado **dentro de su
  transacción**: si algo falla, falla todo.
- **Quién lo implementa.** Los módulos que vienen después, desde su
  `ServiceProvider`, con el `tag()` de Laravel.
- **Sin nadie registrado,** el punto de extensión no hace nada: el módulo
  funciona como hasta ahora.

Con esta spec se usan cuatro puntos de extensión:

| Punto de extensión | Módulo que lo declara | Lo implementa | Criterios |
|--------------------|-----------------------|---------------|-----------|
| `RemovalGuard` (existente; gana `hasEnrollments`) | AcademicStructure | Enrollment | MAT-R10 |
| `WithdrawalHook` (nuevo) | Students | Enrollment | MAT-R05 |
| `YearClosingGuard` (nuevo) | AcademicCalendar | Enrollment | MAT-R16.2 |
| `StudentVisibilityRule` (nuevo) | Students | Enrollment | MAT-R08 |

Los efectos secundarios siguen en el `EventBus`, después del commit.

Un punto de extensión no recibe agregados ni modelos: solo ids, fechas y
valores simples. No abre su propia transacción ni cambia de plantel.

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| **Eventos de dominio después del commit** | No pueden impedir la escritura ni deshacerla: un retiro quedaría hecho aunque la inscripción no terminara (MAT-R05.5). |
| **Eventos síncronos de Laravel dentro de la transacción** | Lo mismo con menos contrato: el que escribe no sabe quién escucha ni qué devuelve, y no puede preguntar «¿cuántos sin resultado?» ni «¿qué estudiantes ve?». |
| **Que Students, AcademicStructure y AcademicCalendar llamen a Enrollment** | Dependencia circular entre módulos (A-06). |
| **Reglas en la base de datos** (*triggers* o claves foráneas entre tablas de módulos) | Algunas no son expresables (la visibilidad, el resultado antes del cierre), y las que sí lo son darían errores de SQL en lugar de mensajes de dominio. Las claves foráneas se mantienen como red de seguridad (`RESTRICT`). |
| **Una política de RLS por cuenta para la visibilidad** | Exigiría llevar la cuenta además del plantel a la sesión de la base de datos (ADR 0011). Es más de lo que exige MAT-R08, que no es un límite entre planteles. Puede reconsiderarse si la visibilidad por persona se vuelve un límite de seguridad. |

## Consecuencias

- **Positivas:**
  - las reglas que cruzan módulos son atómicas y se prueban con un fake
    registrado en la etiqueta;
  - las dependencias siguen yendo en un solo sentido: Enrollment depende de
    Students, AcademicStructure y AcademicCalendar, nunca al revés;
  - SPEC-011 (notas) y F2-02 (pendientes) pueden registrar sus propias
    implementaciones sin tocar a quien escribe.
- **Negativas:**
  - el módulo que escribe no ve en su código quién reacciona; los
    `ServiceProvider` y un test de arquitectura lo documentan;
  - una implementación lenta alarga la transacción de otro módulo. Cada
    implementación usa índices y responde por ids.
- **Regla de arquitectura:** solo el `ServiceProvider` del módulo que
  implementa un punto de extensión lo etiqueta, y ninguna implementación
  importa la Infrastructure del módulo que lo declara.
