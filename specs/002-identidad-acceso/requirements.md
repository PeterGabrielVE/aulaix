---
spec: 002-identidad-acceso
prefijo: IAM
estado: implementada
fecha: 2026-10-10
origen: F1-05, F1-06 y autenticación base (comportamiento existente)
---

# Requisitos — Identidad y acceso

## Contexto

Cada institución gestiona sus propias cuentas: no hay registro público, el
administrador crea los usuarios y los invita por correo. Un usuario pertenece
a una sola institución; la misma persona en dos colegios tiene dos cuentas
independientes. Esta spec documenta el comportamiento **ya implementado**,
que el refactor hexagonal debe conservar.

## Alcance

- **Incluye:** inicio y cierre de sesión, invitaciones, recuperación y cambio
  de contraseña, verificación de email, perfil, gestión de usuarios, roles y
  permisos por institución, pantalla de inicio por rol.
- **Excluye:** resolución del tenant (SPEC-001). Gestión de roles
  personalizados (`gestionar-roles` existe como permiso, pero no tiene
  pantallas todavía).

## Glosario

| Término | Significado |
|---------|-------------|
| Cuenta | Usuario de **una** institución, identificado por su email dentro de ella. |
| Invitación | Enlace por correo con el que un usuario nuevo elige su contraseña y activa la cuenta. |
| Rol base | Uno de los seis roles que tiene toda institución: Administrador, Director, Coordinador, Docente, Representante, Estudiante. |
| Gestor de usuarios | Usuario con el permiso `gestionar-usuarios` (Administrador o Director). |

## Requisitos

### IAM-R01 — Sin registro público

- **IAM-R01.1** — El sistema no deberá ofrecer una pantalla de registro.
- **IAM-R01.2** — El sistema no deberá aceptar peticiones de registro.

### IAM-R02 — Inicio y cierre de sesión

**Historia:** Como usuario, quiero entrar con mi email y contraseña en el
subdominio de mi institución.

- **IAM-R02.1** — El sistema deberá mostrar la pantalla de inicio de sesión
  en el subdominio de una institución activa.
- **IAM-R02.2** — Si el subdominio no corresponde a una institución, entonces
  el sistema deberá responder 404 en lugar de mostrar el inicio de sesión.
- **IAM-R02.3** — Cuando un usuario activo envía credenciales correctas, el
  sistema deberá iniciar su sesión y llevarlo a su pantalla de inicio.
- **IAM-R02.4** — Si la contraseña es incorrecta, entonces el sistema deberá
  rechazar el acceso con el mensaje genérico de credenciales inválidas.
- **IAM-R02.5** — Si las credenciales son de una cuenta de otra institución,
  entonces el sistema deberá rechazar el acceso.
- **IAM-R02.6** — Si un usuario inactivo envía su contraseña correcta,
  entonces el sistema deberá rechazar el acceso indicando que la cuenta está
  inactiva.
- **IAM-R02.7** — Si un usuario inactivo envía una contraseña incorrecta,
  entonces el sistema deberá responder con el mensaje genérico, sin revelar
  que la cuenta existe y está inactiva.
- **IAM-R02.8** — Si se producen 5 intentos fallidos con el mismo email desde
  la misma IP en una institución, entonces el sistema deberá bloquear nuevos
  intentos temporalmente, indicando cuánto falta.
- **IAM-R02.9** — El sistema deberá contar los intentos fallidos por
  institución, de modo que los fallos en una no bloqueen el mismo email en
  otra.
- **IAM-R02.10** — Cuando un usuario cierra sesión, el sistema deberá terminar
  su sesión.

### IAM-R03 — Usuario desactivado con sesión abierta

- **IAM-R03.1** — Si un usuario con sesión abierta es desactivado, entonces
  en su siguiente petición el sistema deberá cerrar su sesión y llevarlo al
  inicio de sesión con el aviso de cuenta inactiva.

### IAM-R04 — Invitación

**Historia:** Como usuario nuevo, quiero activar mi cuenta eligiendo mi
contraseña desde el correo que recibí.

- **IAM-R04.1** — El sistema deberá mostrar la pantalla de activación a partir
  del enlace de invitación.
- **IAM-R04.2** — Cuando un usuario acepta una invitación válida, el sistema
  deberá fijar su contraseña, marcar su email como verificado, iniciar su
  sesión y llevarlo a su pantalla de inicio.
- **IAM-R04.3** — El sistema deberá aceptar una invitación durante 7 días
  desde su envío.
- **IAM-R04.4** — Si la invitación tiene más de 7 días, entonces el sistema
  deberá rechazarla.
- **IAM-R04.5** — Si el token de invitación no es válido, entonces el sistema
  deberá rechazarlo.
- **IAM-R04.6** — Si un usuario desactivado acepta una invitación, entonces el
  sistema deberá fijar su contraseña pero no iniciar su sesión, y mostrar el
  aviso de cuenta inactiva.

### IAM-R05 — Recuperación de contraseña

**Historia:** Como usuario que olvidó su contraseña, quiero recuperarla por
correo sin ayuda del administrador.

- **IAM-R05.1** — El sistema deberá mostrar el formulario de recuperación.
- **IAM-R05.2** — Cuando se solicita la recuperación para una cuenta de la
  institución, el sistema deberá enviar un enlace al subdominio de esa
  institución, que indique que vence en 60 minutos.
- **IAM-R05.3** — Si el email no tiene cuenta en la institución, entonces el
  sistema deberá dar la misma respuesta que para uno registrado y no enviar
  ningún correo.
- **IAM-R05.4** — Si se pide otro enlace para la misma cuenta antes de que
  pase un minuto, entonces el sistema deberá dar la misma respuesta sin enviar
  otro correo.
- **IAM-R05.5** — Si un mismo cliente envía más de 6 solicitudes por minuto,
  entonces el sistema deberá rechazar las siguientes (429).
- **IAM-R05.6** — Cuando se usa un enlace válido, el sistema deberá permitir
  fijar una nueva contraseña y llevar al inicio de sesión.
- **IAM-R05.7** — El sistema deberá aceptar el enlace hasta 60 minutos después
  de solicitarlo y rechazarlo después.
- **IAM-R05.8** — Si un enlace ya se usó, entonces el sistema deberá
  rechazarlo.
- **IAM-R05.9** — Si el token no es válido, entonces el sistema deberá
  rechazarlo.
- **IAM-R05.10** — El sistema deberá mantener los tokens de un mismo email por
  separado en cada institución, y rechazar en una institución un token
  emitido en otra.

### IAM-R06 — Verificación de email

- **IAM-R06.1** — El sistema deberá mostrar el aviso de verificación a un
  usuario con el email sin verificar.
- **IAM-R06.2** — Cuando un usuario abre un enlace de verificación firmado y
  válido, el sistema deberá marcar su email como verificado.
- **IAM-R06.3** — Si el enlace de verificación no coincide con su email,
  entonces el sistema no deberá verificarlo.

### IAM-R07 — Contraseña del usuario autenticado

- **IAM-R07.1** — Cuando un usuario confirma su contraseña correcta, el
  sistema deberá dar por confirmada la acción sensible.
- **IAM-R07.2** — Si la contraseña de confirmación es incorrecta, entonces el
  sistema deberá rechazarla.
- **IAM-R07.3** — Cuando un usuario cambia su contraseña indicando la actual
  correcta, el sistema deberá guardar la nueva.
- **IAM-R07.4** — Si la contraseña actual indicada es incorrecta, entonces el
  sistema no deberá cambiarla.

### IAM-R08 — Perfil

- **IAM-R08.1** — El sistema deberá mostrar al usuario su perfil.
- **IAM-R08.2** — Cuando un usuario cambia su nombre o email, el sistema
  deberá guardarlos y, si cambió el email, marcarlo como no verificado.
- **IAM-R08.3** — Si el email no cambia, entonces el sistema deberá conservar
  su estado de verificación.
- **IAM-R08.4** — Cuando un usuario elimina su cuenta indicando su contraseña
  correcta, el sistema deberá cerrar su sesión y eliminar la cuenta.
- **IAM-R08.5** — Si la contraseña indicada es incorrecta, entonces el sistema
  no deberá eliminar la cuenta.

### IAM-R09 — Gestión de usuarios

**Historia:** Como gestor de usuarios, quiero dar de alta y mantener las
cuentas de mi institución.

- **IAM-R09.1** — Si un visitante sin sesión accede a la gestión de usuarios,
  entonces el sistema deberá llevarlo al inicio de sesión.
- **IAM-R09.2** — Si un usuario sin el permiso `gestionar-usuarios` accede a
  la gestión de usuarios, entonces el sistema deberá responder 403.
- **IAM-R09.3** — El sistema deberá listar, paginados de 20 en 20 y ordenados
  por nombre, los usuarios de la institución, con sus roles y si tienen la
  invitación pendiente, y permitir filtrarlos por nombre o email.
- **IAM-R09.4** — El sistema no deberá listar ni permitir editar usuarios de
  otra institución (404).
- **IAM-R09.5** — Cuando un gestor crea un usuario con nombre, email y al
  menos un rol, el sistema deberá crearlo activo y enviarle una invitación.
- **IAM-R09.6** — Si el email ya pertenece a otro usuario de la misma
  institución, entonces el sistema deberá rechazarlo.
- **IAM-R09.7** — El sistema deberá permitir el mismo email en instituciones
  distintas.
- **IAM-R09.8** — Si no se indica ningún rol, entonces el sistema deberá
  rechazar el alta o la edición.
- **IAM-R09.9** — Si se indica un rol que no existe en la institución,
  entonces el sistema deberá rechazarlo.
- **IAM-R09.10** — El sistema deberá permitir asignar varios roles a un mismo
  usuario.
- **IAM-R09.11** — Cuando un gestor edita un usuario, el sistema deberá
  permitir cambiar su nombre, email, roles y estado; si cambia el email, deberá
  marcarlo como no verificado.
- **IAM-R09.12** — Cuando un gestor reenvía la invitación a un usuario que no
  la ha aceptado, el sistema deberá enviar una nueva.
- **IAM-R09.13** — Si el usuario ya activó su cuenta, entonces el sistema
  deberá rechazar el reenvío (409).

### IAM-R10 — Reglas de asignación de roles

**Historia:** Como institución, quiero que nadie pueda dejarnos sin
administración ni escalar privilegios.

- **IAM-R10.1** — Si un gestor que no es Administrador intenta dar o quitar el
  rol de Administrador a cualquier usuario, incluido él mismo, entonces el
  sistema deberá rechazarlo.
- **IAM-R10.2** — Mientras un Director edita a un Administrador, el sistema
  deberá permitir cambiar sus demás datos si conserva el rol de Administrador.
- **IAM-R10.3** — Si un Administrador intenta quitarse a sí mismo el rol de
  Administrador, entonces el sistema deberá rechazarlo.
- **IAM-R10.4** — Si un gestor intenta quedarse sin ningún rol que le permita
  gestionar usuarios, entonces el sistema deberá rechazarlo.
- **IAM-R10.5** — Si un gestor intenta desactivar su propia cuenta, entonces
  el sistema deberá rechazarlo.
- **IAM-R10.6** — El sistema deberá permitir que un Administrador se añada
  roles a sí mismo.

### IAM-R11 — Roles y permisos por institución

- **IAM-R11.1** — El sistema deberá crear en cada institución los seis roles
  base con estos permisos:

  | Rol | Permisos |
  |-----|----------|
  | Administrador | todos (`gestionar-usuarios`, `gestionar-roles`, `ver-estudiantes`, `gestionar-calificaciones`, `ver-calificaciones`) |
  | Director | `gestionar-usuarios`, `ver-estudiantes`, `ver-calificaciones` |
  | Coordinador | `ver-estudiantes`, `gestionar-calificaciones`, `ver-calificaciones` |
  | Docente | `ver-estudiantes`, `gestionar-calificaciones`, `ver-calificaciones` |
  | Representante | `ver-calificaciones` |
  | Estudiante | `ver-calificaciones` |

- **IAM-R11.2** — El sistema deberá tratar un rol con el mismo nombre en dos
  instituciones como dos roles independientes.
- **IAM-R11.3** — El sistema deberá conceder a un usuario solo los permisos de
  los roles que tiene en su propia institución.
- **IAM-R11.4** — Cuando se añaden roles base nuevos, el sistema deberá
  crearlos también en las instituciones existentes.

### IAM-R12 — Pantalla de inicio por rol

- **IAM-R12.1** — Cuando un usuario entra, el sistema deberá llevarlo a la
  pantalla de inicio de su rol.
- **IAM-R12.2** — Mientras un usuario tiene varios roles, el sistema deberá
  usar el de mayor prioridad: Administrador > Director > Coordinador >
  Docente > Representante > Estudiante.
- **IAM-R12.3** — Si un usuario no tiene ningún rol, entonces el sistema
  deberá mostrarle un aviso en lugar de una pantalla de inicio.
- **IAM-R12.4** — Si un usuario intenta abrir la pantalla de inicio de un rol
  que no tiene, entonces el sistema deberá denegarlo.

## Requisitos no funcionales

- **IAM-NF01** (seguridad) — Contraseñas con hash adaptativo (bcrypt).
  Tokens de invitación y recuperación guardados hasheados, de un solo uso y
  con institución (S-08).
- **IAM-NF02** (privacidad) — Ninguna respuesta revela si un email tiene
  cuenta (S-05).
- **IAM-NF03** (seguridad) — Los parámetros con contraseñas o tokens se marcan
  como `#[\SensitiveParameter]` (S-07).

## Hallazgos abiertos (requieren aprobación — cambian comportamiento)

| ID | Hallazgo | Riesgo | Propuesta |
|----|----------|--------|-----------|
| **IAM-H01** | Desde su perfil, un Administrador puede **borrar su propia cuenta**, aunque IAM-R10.3 le impide quitarse el rol. | Una institución puede quedarse sin ningún Administrador, y nadie podría volver a asignar ese rol (IAM-R10.1). | Nuevo criterio: *Si el único Administrador activo de la institución intenta eliminar su cuenta, entonces el sistema deberá rechazarlo.* Mismo análisis para la desactivación del último Administrador por un tercero. |
| **IAM-H02** | `HandleInertiaRequests` comparte el modelo `User` completo en cada página (`institution_id`, `status`, fechas…). | Exposición de datos innecesaria al navegador (S-10). | Compartir un DTO explícito: `id`, `name`, `email`, `roles`. |
| **IAM-H03** | La unicidad del email en el perfil no filtra por institución; funciona solo porque la RLS oculta las demás filas. | Bajo, pero frágil e inconsistente con la gestión de usuarios (IAM-R09.6). | Misma regla que `UserRequest`, dentro del caso de uso. |
| **IAM-H04** | La búsqueda de usuarios inserta el texto en el `LIKE` sin escapar `%` ni `_`. | Bajo (va parametrizado). | Escapar comodines (S-09). |
| **IAM-H05** | Las tablas de roles y asignaciones de Spatie están exentas de RLS: su aislamiento depende solo de que la aplicación fije el *team* correcto. | Medio: un error en el código (team sin fijar o equivocado) mostraría o asignaría roles de otra institución, y la base de datos no lo impediría. | Evaluar una política RLS que permita filas sin tenant solo para lectura del registro de Spatie, o tests de aislamiento dedicados a esas tablas. Requiere un ADR. |

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| IAM-R01.1 | `tests/Feature/Auth/RegistrationTest.php` › «there is no public registration screen» |
| IAM-R01.2 | `tests/Feature/Auth/RegistrationTest.php` › «there is no public registration endpoint» |
| IAM-R02.1 | `tests/Feature/Auth/AuthenticationTest.php` › «login screen can be rendered» |
| IAM-R02.2 | `tests/Feature/Auth/AuthenticationTest.php` › «unknown subdomains are rejected» |
| IAM-R02.3 | `tests/Feature/Auth/AuthenticationTest.php` › «users can authenticate using the login screen» |
| IAM-R02.4 | `tests/Feature/Auth/AuthenticationTest.php` › «users can not authenticate with invalid password» |
| IAM-R02.5 | `tests/Feature/Auth/AuthenticationTest.php` › «users can not authenticate on another institution's subdomain» |
| IAM-R02.6 | `tests/Feature/Auth/AuthenticationTest.php` › «inactive users can not authenticate» |
| IAM-R02.7 | `tests/Feature/Auth/AuthenticationTest.php` › «inactive users with a wrong password get the generic error» |
| IAM-R02.8 | Sin test propio (comportamiento heredado de Breeze). **Pendiente**: lo añade SPEC-000 T-09, antes de activar la validación de specs. |
| IAM-R02.9 | `tests/Feature/Auth/AuthenticationTest.php` › «failed logins in one institution do not lock out the same email in another» |
| IAM-R02.10 | `tests/Feature/Auth/AuthenticationTest.php` › «users can logout» |
| IAM-R03.1 | `tests/Feature/Auth/AuthenticationTest.php` › «a user deactivated mid-session is logged out on their next request» |
| IAM-R04.1 | `tests/Feature/Auth/InvitationTest.php` › «the invitation screen can be rendered» |
| IAM-R04.2 | `tests/Feature/Auth/InvitationTest.php` › «accepting an invitation sets the password, verifies the email and logs the user in» |
| IAM-R04.3 | `tests/Feature/Auth/InvitationTest.php` › «an invitation is still valid days later, unlike a password reset link» |
| IAM-R04.4 | `tests/Feature/Auth/InvitationTest.php` › «an invitation expires after a week» |
| IAM-R04.5 | `tests/Feature/Auth/InvitationTest.php` › «an invalid invitation token is rejected» |
| IAM-R04.6 | `tests/Feature/Auth/InvitationTest.php` › «a deactivated user who accepts an invitation is not logged in» |
| IAM-R05.1 | `tests/Feature/Auth/PasswordResetTest.php` › «reset password link screen can be rendered» |
| IAM-R05.2 | `tests/Feature/Auth/PasswordResetTest.php` › «reset password link can be requested»; `tests/Feature/Auth/PasswordResetTest.php` › «the reset email links to the institution subdomain and states the 60 minute expiry» |
| IAM-R05.3 | `tests/Feature/Auth/PasswordResetTest.php` › «an unknown email gets the same answer as a registered one and no email is sent» |
| IAM-R05.4 | `tests/Feature/Auth/PasswordResetTest.php` › «a second request within the broker throttle gets the same answer without sending another email» |
| IAM-R05.5 | `tests/Feature/Auth/PasswordResetTest.php` › «the forgot password form is rate limited per client» |
| IAM-R05.6 | `tests/Feature/Auth/PasswordResetTest.php` › «reset password screen can be rendered»; `tests/Feature/Auth/PasswordResetTest.php` › «password can be reset with valid token» |
| IAM-R05.7 | `tests/Feature/Auth/PasswordResetTest.php` › «a reset link still works 59 minutes after it was requested»; `tests/Feature/Auth/PasswordResetTest.php` › «a reset link expires after 60 minutes» |
| IAM-R05.8 | `tests/Feature/Auth/PasswordResetTest.php` › «a reset link can only be used once» |
| IAM-R05.9 | `tests/Feature/Auth/PasswordResetTest.php` › «an invalid reset token is rejected» |
| IAM-R05.10 | `tests/Feature/Auth/PasswordResetTest.php` › «reset tokens for the same email are kept separately per institution»; `tests/Feature/Auth/PasswordResetTest.php` › «a reset token from one institution can not be used in another» |
| IAM-R06.1 | `tests/Feature/Auth/EmailVerificationTest.php` › «email verification screen can be rendered» |
| IAM-R06.2 | `tests/Feature/Auth/EmailVerificationTest.php` › «email can be verified» |
| IAM-R06.3 | `tests/Feature/Auth/EmailVerificationTest.php` › «email is not verified with invalid hash» |
| IAM-R07.1 | `tests/Feature/Auth/PasswordConfirmationTest.php` › «confirm password screen can be rendered»; `tests/Feature/Auth/PasswordConfirmationTest.php` › «password can be confirmed» |
| IAM-R07.2 | `tests/Feature/Auth/PasswordConfirmationTest.php` › «password is not confirmed with invalid password» |
| IAM-R07.3 | `tests/Feature/Auth/PasswordUpdateTest.php` › «password can be updated» |
| IAM-R07.4 | `tests/Feature/Auth/PasswordUpdateTest.php` › «correct password must be provided to update password» |
| IAM-R08.1 | `tests/Feature/ProfileTest.php` › «profile page is displayed» |
| IAM-R08.2 | `tests/Feature/ProfileTest.php` › «profile information can be updated» |
| IAM-R08.3 | `tests/Feature/ProfileTest.php` › «email verification status is unchanged when the email address is unchanged» |
| IAM-R08.4 | `tests/Feature/ProfileTest.php` › «user can delete their account» |
| IAM-R08.5 | `tests/Feature/ProfileTest.php` › «correct password must be provided to delete account» |
| IAM-R09.1 | `tests/Feature/UserManagementTest.php` › «guests are sent to the login screen» |
| IAM-R09.2 | `tests/Feature/UserManagementTest.php` › «users without the gestionar-usuarios permission can not manage users» |
| IAM-R09.3 | `tests/Feature/UserManagementTest.php` › «administrators can list the users of their institution»; `tests/Feature/UserManagementTest.php` › «a director can manage the users of their institution» |
| IAM-R09.4 | `tests/Feature/UserManagementTest.php` › «the user list never includes users of another institution»; `tests/Feature/UserManagementTest.php` › «a user of another institution can not be edited» |
| IAM-R09.5 | `tests/Feature/UserManagementTest.php` › «an administrator creates a user with a role and an invitation is sent» |
| IAM-R09.6 | `tests/Feature/UserManagementTest.php` › «the email must be unique within the institution» |
| IAM-R09.7 | `tests/Feature/UserManagementTest.php` › «the same email can be used in a different institution» |
| IAM-R09.8 | `tests/Feature/UserManagementTest.php` › «at least one role is required» |
| IAM-R09.9 | `tests/Feature/UserManagementTest.php` › «only roles that exist in the institution can be assigned» |
| IAM-R09.10 | `tests/Feature/UserManagementTest.php` › «a user can be given several roles in the same institution» |
| IAM-R09.11 | `tests/Feature/UserManagementTest.php` › «the edit screen shows the user with their role»; `tests/Feature/UserManagementTest.php` › «an administrator can change a user's role and deactivate them» |
| IAM-R09.12 | `tests/Feature/UserManagementTest.php` › «a pending invitation can be resent» |
| IAM-R09.13 | `tests/Feature/UserManagementTest.php` › «an invitation can not be resent to a user who already activated their account» |
| IAM-R10.1 | `tests/Feature/UserManagementTest.php` › «a director can not make anyone an administrador»; `tests/Feature/UserManagementTest.php` › «a director can not take the administrador role away» |
| IAM-R10.2 | `tests/Feature/UserManagementTest.php` › «a director can edit an administrador without touching that role» |
| IAM-R10.3 | `tests/Feature/UserManagementTest.php` › «an administrador can not swap their own role for director» |
| IAM-R10.4 | `tests/Feature/UserManagementTest.php` › «a director can not give up managing users»; `tests/Feature/UserManagementTest.php` › «administrators can not deactivate themselves or give up managing users» |
| IAM-R10.5 | `tests/Feature/UserManagementTest.php` › «administrators can not deactivate themselves or give up managing users» |
| IAM-R10.6 | `tests/Feature/UserManagementTest.php` › «an administrador can add roles to themselves» |
| IAM-R11.1 | `tests/Feature/RolePermissionTest.php` › «every institution gets the six base roles»; `tests/Feature/RolePermissionTest.php` › «each base role carries its permissions» |
| IAM-R11.2 | `tests/Feature/RolePermissionTest.php` › «the same role name exists independently per institution» |
| IAM-R11.3 | `tests/Feature/RolePermissionTest.php` › «a user only has the permissions granted within their own institution»; `tests/Feature/RolePermissionTest.php` › «assigning a role in one institution does not grant it in another»; `tests/Feature/RolePermissionTest.php` › «the same person can hold different roles in each institution» |
| IAM-R11.4 | `tests/Feature/RolePermissionTest.php` › «the roles migration adds director and coordinador to existing institutions» |
| IAM-R12.1 | `tests/Feature/RoleHomeTest.php` › «each role is sent to its own home screen after login» |
| IAM-R12.2 | `tests/Feature/RoleHomeTest.php` › «a user with several roles lands on the highest-priority one»; `tests/Feature/RoleHomeTest.php` › «a director who also teaches lands on the director home» |
| IAM-R12.3 | `tests/Feature/RoleHomeTest.php` › «a user without a role sees a notice instead of a home screen» |
| IAM-R12.4 | `tests/Feature/RoleHomeTest.php` › «a role can not open another role's home screen» |
