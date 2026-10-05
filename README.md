# AulaX

Plataforma académica multi-tenant. Fase 1 (MVP académico): modelo de
instituciones con aislamiento por `institution_id` + Row Level Security en
PostgreSQL, resolución de tenant por subdominio, catálogos globales
(materias MPPE, estados/municipios/parroquias), autenticación y roles por
institución.

**Stack:** Laravel 13 + Vue 3 (Inertia.js) + PostgreSQL 16, con un módulo de
IA desacoplado (FastAPI), todo orquestado con Docker Compose.

## Arquitectura

- **Multi-tenancy**: base de datos compartida, aislada por `institution_id` y
  reforzada con Row Level Security de PostgreSQL (`FORCE ROW LEVEL SECURITY`,
  ver `database/migrations/2025_01_01_000005_enable_row_level_security_on_tenant_tables.php`).
  El scope de Eloquent (`App\Concerns\BelongsToInstitution`) es una
  conveniencia de aplicación; la RLS es el límite de seguridad real.
- **Resolución de tenant**: cada institución vive en
  `https://{subdomain}.{APP_DOMAIN}`. `App\Http\Middleware\ResolveTenant`
  resuelve el subdominio (capturado como parámetro de ruta `{tenant}` en
  `routes/web.php`) a una `Institution`, y a partir de ahí configura el
  scope de Eloquent, la sesión de Postgres para RLS, y el "team" de
  spatie/laravel-permission — todo en un solo lugar.
- **Dominio central** (`https://{APP_DOMAIN}`, sin subdominio): selector de
  institución (`resources/js/Pages/SelectInstitution.vue`), nunca pasa por
  `ResolveTenant`.
- **Roles y permisos**: `spatie/laravel-permission` con su feature de
  *teams*, usando `institution_id` como `team_foreign_key` — el mismo
  nombre de rol (p. ej. "Docente") existe de forma independiente en cada
  institución.
- **Módulo de IA**: `docker/ai-service` es un servicio FastAPI
  independiente. Laravel nunca lo llama directamente — solo a través de
  `App\Contracts\AIServiceClient`, así la implementación es intercambiable.
  En esta fase no hay funcionalidades de IA reales, solo el cableado.

## Requisitos

- Docker y Docker Compose
- (Opcional) permisos de administrador para editar el archivo hosts local

## Puesta en marcha

1. Copia el archivo de entorno:

   ```bash
   cp .env.example .env
   ```

2. Añade las instituciones de desarrollo a tu archivo hosts
   (`C:\Windows\System32\drivers\etc\hosts` en Windows,
   `/etc/hosts` en Linux/Mac — requiere permisos de administrador):

   ```
   127.0.0.1 aulaix.test
   127.0.0.1 demo.aulaix.test
   127.0.0.1 demo2.aulaix.test
   ```

3. Levanta el stack:

   ```bash
   docker compose up -d --build
   ```

4. Instala dependencias PHP, genera la key, migra y siembra datos:

   ```bash
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```

5. Abre `http://aulaix.test` → selector de institución → elige
   "Colegio Demo Uno" → serás redirigido a `http://demo.aulaix.test/login`.

   Usuarios sembrados (contraseña `password`), uno por rol en cada
   institución — cada rol aterriza en su propia pantalla de inicio:

   | Rol           | Colegio Demo Uno                  | Colegio Demo Dos                   |
   |---------------|-----------------------------------|------------------------------------|
   | Administrador | `admin@demo.aulaix.test`          | `admin@demo2.aulaix.test`          |
   | Docente       | `docente@demo.aulaix.test`        | `docente@demo2.aulaix.test`        |
   | Representante | `representante@demo.aulaix.test`  | `representante@demo2.aulaix.test`  |
   | Estudiante    | `estudiante@demo.aulaix.test`     | `estudiante@demo2.aulaix.test`     |

## Autenticación

- **Sin registro público.** Solo el administrador de cada institución crea
  cuentas (menú *Usuarios*), les asigna un rol y el sistema les envía una
  invitación por correo para que elijan su contraseña (el enlace vence en 7
  días; se puede reenviar). En desarrollo los correos llegan a Mailhog.
- **Usuarios activos/inactivos.** Un usuario inactivo no puede iniciar
  sesión, y si ya tenía sesión abierta se cierra en su siguiente petición.
  Un administrador no puede desactivarse ni quitarse el rol a sí mismo.
- **Redirección por rol.** `/dashboard` redirige a la pantalla de inicio del
  rol (`User::HOME_ROUTES`); con varios roles gana el de mayor prioridad.
- **Aislamiento.** Los tokens de restablecimiento/invitación
  (`password_reset_tokens`) tienen `institution_id` y RLS, igual que
  `users`, y el límite de intentos de login es por institución: el mismo
  correo en dos instituciones son cuentas independientes.

## Servicios

| Servicio     | URL / puerto local              | Descripción                          |
|--------------|----------------------------------|---------------------------------------|
| `nginx`      | http://aulaix.test (puerto 80)  | Sirve la app Laravel                  |
| `node`       | localhost:5173                  | Vite dev server (HMR)                 |
| `postgres`   | localhost:5432                  | Base de datos                         |
| `redis`      | interno                         | Cache / sesiones                      |
| `mailhog`    | http://localhost:8025           | Bandeja de correo de desarrollo       |
| `ai-service` | http://localhost:8001/health    | Esqueleto del módulo de IA (FastAPI)  |

## Comandos útiles

```bash
# Tests (corren contra la base de datos "aulaix_testing", separada de la de desarrollo)
docker compose exec app php artisan test

# Ver que el módulo de IA responde
docker compose exec app php artisan ai:health

# Tinker
docker compose exec app php artisan tinker
```

## Notas sobre los datos de catálogo

Los estados de Venezuela (`database/seeders/GeographicCatalogSeeder.php`)
están completos (24), pero municipios y parroquias solo incluyen una
muestra representativa (la capital de cada estado) —
`database/seeders/data/venezuela-divisions.json`. No es un import completo
de DIVIPOLA (335 municipios, 1000+ parroquias); reemplaza ese fixture por
el dataset oficial cuando el catálogo deba ser exhaustivo. Los códigos
(`code`) son identificadores internos secuenciales, no códigos DIVIPOLA
oficiales.
