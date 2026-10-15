---
estado: aceptada
fecha-aceptación: 2026-10-15
fecha: 2026-10-15
spec: 004-identidad-global
modifica: ADR 0004 (decisiones 6 y 7)
---

# ADR 0006 — Aislamiento de la identidad global sin funciones privilegiadas

## Contexto

El ADR 0004 decidió dos mecanismos que, al diseñar SPEC-004 contra el código
y PostgreSQL, resultan inviables o contradictorios:

1. **Decisión 6: funciones `SECURITY DEFINER`** (`auth_lookup`,
   `account_self`) para leer `users` sin plantel activo. Una función así
   solo sirve si su dueño puede saltarse la RLS. Ese rol no puede crearlo
   una migración, porque el rol de la aplicación es `NOCREATEROLE`. Y para
   que una migración le traspase la función, el rol de la aplicación tendría
   que ser **miembro** de ese rol privilegiado: con un `SET ROLE` se saltaría
   toda la RLS del sistema. Se perdería justo lo que se quería proteger
   (S-03).
2. **Decisión 7: tabla `legacy_credentials`** para las cuentas históricas con
   el mismo email. Como `users.email` pasa a ser único, obliga a reducir esas
   filas a una sola **antes** de que el titular verifique nada. Eso es una
   fusión automática, prohibida por la decisión de producto Q1.

## Decisión

### A. RLS de `users` por contexto, con políticas por comando

| Comando | Política |
|---------|----------|
| `SELECT`, `UPDATE`, `DELETE` | Fila visible si **no hay plantel activo**, o si la persona tiene una membresía (cualquier estado) en el plantel activo. |
| `INSERT` | Permitido: crear una cuenta no revela datos de nadie. |

- Con un plantel activo (casi todo el código: cada petición a un subdominio)
  la base de datos solo deja ver a los miembros de ese plantel, incluso con
  SQL directo (S-01, IDG-NF01).
- Sin plantel activo (dominio central: login, recuperación, cuenta propia,
  invitaciones; consola y tareas programadas) `users` es legible entera.
  Ese código es pequeño y se concentra en `IdentityAccess`. Un test de
  arquitectura impide que otros módulos lo lean sin plantel activo.

### B. Cuentas históricas duplicadas: filas separadas hasta consolidar

- `users.consolidation_state`: `null` (cuenta normal), `pending` (comparte
  email con otras, nombres coincidentes) o `review` (nombres distintos,
  IDG-R09.5).
- Índice único **parcial**: `UNIQUE (email) WHERE consolidation_state IS NULL`.
  Las filas duplicadas conviven, marcadas, cada una con su contraseña y su
  única membresía. Así se cumple IDG-R09.3 sin tabla aparte: cada contraseña
  histórica abre solo su plantel.
- La consolidación, verificada por el titular o resuelta por soporte, mueve
  membresías y roles a una sola fila y borra las demás. Hasta entonces no se
  fusiona nada.

## Consecuencias

**Positivas**
- No hace falta ningún rol privilegiado ni pasos manuales de base de datos
  en el despliegue.
- La transición de las cuentas duplicadas es literal: nada se une sin
  verificación.

**Negativas**
- Sin plantel activo, `users` no tiene protección de la base de datos frente
  a un error de código. Lo mitigan dos cosas: el código del dominio central
  es pequeño y está aislado en un módulo, y un test de arquitectura impide
  usar el modelo de cuenta desde otros módulos.
- `users.email` solo es único entre cuentas normales. Las búsquedas por email
  deben tener en cuenta las filas en consolidación (lo hace el repositorio).

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| `SECURITY DEFINER` con un rol creado en el aprovisionamiento | Exige pasos manuales con superusuario en cada entorno, y el traspaso de las funciones sigue requiriendo que el rol de la app pueda asumir el rol privilegiado. |
| Base de datos (o esquema y rol) de identidad separada | Transacciones distribuidas entre identidad y planteles (crear cuenta y membresía a la vez); demasiada infraestructura para esta escala. |
| `legacy_credentials` (ADR 0004 §7) | Obliga a fusionar las filas antes de verificar; ver Contexto. |
