---
estado: aceptada
fecha-aceptación: 2026-10-10
fecha: 2026-10-10
backlog: F1-06, F1-07
---

# ADR 0004 — Identidad global con membresías por plantel

## Contexto

F1-06 se implementó con **cuentas por plantel**: `users` tiene
`institution_id` y RLS, y el mismo correo en dos colegios son dos cuentas con
contraseñas distintas. Pero F1-07 pide que un «usuario con varios planteles
elija cuál abrir». Producto decidió (SPEC-004, «Decisiones incorporadas»)
que la identidad pertenece a la persona y que la relación con cada plantel la
administra el plantel.

## Decisión

1. **Identidad global.** `users` deja de tener `institution_id`: guarda el
   email normalizado y único, el nombre y las credenciales. Solo su titular la
   modifica.
2. **Membresías** (`institution_memberships`; el documento de producto las llama
   `school_memberships`, aquí se sigue la convención `institution` del proyecto):
   relación persona ↔ plantel, con `institution_id` y RLS. El estado (activa,
   suspendida con motivo, terminada) y los roles son **por membresía**.
3. **Invitaciones** (`invitations`): entidad propia con `institution_id` y RLS
   (email, nombre, roles, caducidad y estado). La membresía nace cuando se
   **acepta** la invitación, nunca al crearla.
4. **Autenticación centralizada** en el dominio central. Los subdominios
   reciben la sesión mediante un token de traspaso de un solo uso (60 s) y
   verifican la membresía activa en cada petición.
5. Los roles siguen en Spatie con *teams* = `institution_id`. El modelo con
   roles es el usuario global.
6. **Aislamiento de datos personales.** `users` mantiene RLS con una política
   por membresía:
   `USING (EXISTS (SELECT 1 FROM institution_memberships m WHERE m.user_id = users.id AND m.institution_id = <tenant actual>))`.
   Un plantel solo ve a las personas que son miembros suyos. El login central
   y la gestión de la propia cuenta, que necesitan leer `users` sin tenant,
   usan funciones `SECURITY DEFINER` con una superficie mínima:
   - `auth_lookup(email)` → id, hash, estado de consolidación;
   - `account_self(user_id)` → solo los datos de la propia cuenta.

   El rol de la aplicación sigue sin `BYPASSRLS` (S-03).
7. **Sin fusión automática.** Las cuentas históricas que comparten email se
   consolidan solo cuando el titular demuestra que controla el correo.
   Mientras tanto, cada contraseña histórica abre solo su plantel
   (tabla temporal `legacy_credentials`, que se elimina al consolidar).

## Consecuencias

**Positivas**
- Una contraseña por persona; se cumple F1-07.
- Base para el portal del representante (F2-07) y las apps móviles (F7-01,
  F7-02).
- El aislamiento de datos personales sigue en la base de datos.
- Ninguna acción de un plantel altera la identidad de la persona ni su acceso
  a otros.

**Negativas / costes**
- Migración en dos tiempos: migración técnica y consolidación por cada
  titular. Durante la transición conviven dos caminos de autenticación.
- Las funciones `SECURITY DEFINER` son código privilegiado: requieren revisión
  de seguridad específica y tests propios.
- La sesión por subdominio exige el token de traspaso.
- La eliminación de cuentas depende de una tabla de conservación legal que
  todavía no existe (SPEC-004, dependencia D1). Hasta entonces, el ciclo de
  vida llega a `closed` (acceso revocado y datos intactos), nunca a `deleted`.

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| Mantener cuentas por plantel | No cumple F1-07. |
| Fusionar automáticamente por email | Podría unir a personas distintas y dar acceso a planteles ajenos (decisión de producto Q1). |
| `users` sin RLS, protegida solo por el código | Rompe S-01: un error de código expondría los datos personales de todo el sistema. |
| Cookie de sesión compartida (`SESSION_DOMAIN=.dominio`) | Una sola cookie para todos los subdominios amplía el impacto de un XSS en un plantel a todos los demás. |
| Base de datos de identidad separada | Más infraestructura y transacciones distribuidas, sin beneficio a esta escala. |
