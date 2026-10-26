---
adr: 0010
título: Identidad de plataforma y lecturas de un plantel sin saltar la RLS
estado: aceptado
fecha: 2026-10-26
spec: 014-superadministracion
---

# ADR 0010 — Identidad de plataforma y lecturas de un plantel sin saltar la RLS

## Contexto

SPEC-014 crea un panel para operar la plataforma. El roadmap y la
constitución le ponen restricciones:

- el operador es un actor de plataforma, no un rol de plantel, y entra con
  MFA;
- no puede usarse `BYPASSRLS` (S-03);
- el ADR 0006 descartó las funciones `SECURITY DEFINER`, porque obligarían
  al rol de la aplicación a poder asumir un rol privilegiado.

Aun así, el panel necesita cifras de cada plantel (miembros, estudiantes,
última actividad), y esas tablas solo se leen con el plantel activo.

## Decisión

### 1. Identidad de plataforma aparte

- **Tablas propias.** `platform_operators`, `platform_operator_invitations`
  y `platform_operator_recovery_codes`, con la política de
  `RowLevelSecurity::enableOutsideTenants`: invisibles mientras haya un
  plantel activo, igual que `platform_audit_entries`.
- **Guard propio.** Un guard de sesión `platform` con su propio proveedor.
  El guard `web` de los planteles no conoce a los operadores, ni el
  `platform` a las cuentas.
- **Dominio propio.** El panel se sirve solo en `admin.APP_DOMAIN`.
  `admin` ya es un subdominio reservado (TEN-R02.3). Con
  `SESSION_DOMAIN=null`, su cookie es del propio host y no se comparte.
- **TOTP con librería.** Se usan `pragmarx/google2fa` y
  `bacon/bacon-qr-code`, las mismas que usa Laravel Fortify. El secreto se
  guarda cifrado con la clave de la aplicación, y cada paso de tiempo se
  acepta una sola vez.

### 2. Lecturas de un plantel: activación temporal y acotada

Para leer el uso de un plantel, un único adaptador de Platform hace esto:

1. activa ese plantel con `TenantActivator`, como hace el traslado (ADR
   0008);
2. llama a consultas públicas de agregados de cada módulo: solo cuentan,
   nunca devuelven filas de personas;
3. restaura el estado anterior en `finally`.

La lectura corre bajo la RLS de ese plantel, así que la base de datos sigue
limitando qué se ve. Un test de arquitectura impide que el resto de Platform
active planteles.

La bitácora de lo que consulta el operador se escribe después de restaurar,
para que vaya a la bitácora de plataforma y no a la del plantel.

## Alternativas

| Alternativa | Por qué no |
|-------------|------------|
| Operador como marca en la cuenta global (SPEC-004) | Mezcla identidades: una cuenta comprometida de un plantel podría llegar al panel, y viceversa. El roadmap pide separarlas. |
| Rol con `BYPASSRLS` para el panel | Prohibido por S-03: un error de código expondría todos los planteles. |
| Funciones `SECURITY DEFINER` de superficie mínima | Descartadas por el ADR 0006: exigen un rol privilegiado que el rol de la aplicación pueda asumir. |
| Tabla de cifras precalculadas por plantel (instantáneas) | Necesita un proceso que las mantenga, con desfase y otra tabla sin RLS que proteger. Se reconsidera si la lista necesita cifras de 500 planteles a la vez. Hoy solo se leen las de uno, en su detalle. |
| TOTP propio (RFC 6238 a mano) | Pocas líneas, pero criptografía propia y además un generador de QR. Las dos librerías son estándar y están auditadas por la comunidad de Laravel. |
| Código por correo o WebAuthn | Decidido en los requisitos: TOTP. |

## Consecuencias

- **Dependencias nuevas de producción:** `pragmarx/google2fa` y
  `bacon/bacon-qr-code`.
- **Activar planteles ya no es exclusivo de Students:** Platform también
  puede hacerlo, pero en un solo adaptador y vigilado por un test de
  arquitectura.
- **Consultas de agregados con autorización propia:** los módulos dueños
  exponen consultas de agregados que no usan los permisos de plantel. Las
  protegen un test de arquitectura (solo `AulaX\Platform\Infrastructure`
  puede usarlas) y un `PlatformReadScope` que solo crea la lectura
  acotada (SPEC-014, V2).
- **Fin de la lista del entorno:** la lista de operadores de
  `IDENTITY_CONSOLIDATION_OPERATORS` (IDG-R11.3) desaparece. El comando de
  consolidación pregunta a Platform.
