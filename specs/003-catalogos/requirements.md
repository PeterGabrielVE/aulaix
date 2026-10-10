---
spec: 003-catalogos
prefijo: CAT
estado: implementada
fecha: 2026-10-10
origen: F1-04 (comportamiento existente)
---

# Requisitos — Catálogos globales

## Contexto

Las instituciones comparten datos de referencia oficiales: la división
político-territorial de Venezuela y las áreas de formación del MPPE. Son
iguales para todos los tenants, se cargan con un seeder que puede ejecutarse
en cada despliegue, y alimentarán el perfil de la institución y el plan de
estudios. Esta spec documenta el comportamiento **ya implementado**.

## Alcance

- **Incluye:** estados, municipios, parroquias y materias; su sincronización.
- **Excluye:** edición de catálogos desde la interfaz (no existe).

## Glosario

| Término | Significado |
|---------|-------------|
| Entidad federal | Estado (o Distrito Capital), con código ISO 3166-2 (`VE-A`). |
| Código interno | Código por posición de municipios (`VE-A-01`) y parroquias (`VE-A-01-01`). |
| Área de formación | Materia del currículo del MPPE, con código (`MAT`) y nivel educativo. |
| Sincronización | Ejecución del seeder de catálogos: deja la base igual al catálogo de referencia. |

## Requisitos

### CAT-R01 — Catálogo geográfico

- **CAT-R01.1** — El sistema deberá contener las 24 entidades federales, 335
  municipios y 1.140 parroquias de Venezuela, y toda entidad tendrá al menos
  un municipio, y todo municipio al menos una parroquia.
- **CAT-R01.2** — El sistema deberá identificar cada entidad federal por su
  código ISO 3166-2 y su nombre vigente (p. ej. `VE-W` La Guaira, sin
  «Vargas»).
- **CAT-R01.3** — El sistema deberá permitir obtener los municipios de una
  entidad y las parroquias de un municipio.

### CAT-R02 — Catálogo de materias

- **CAT-R02.1** — El sistema deberá contener las áreas de formación del
  currículo vigente del MPPE, con código, nombre y nivel educativo (Inicial,
  Primaria, Media General), con al menos una materia en cada nivel.

### CAT-R03 — Sincronización

- **CAT-R03.1** — Cuando se sincronizan los catálogos sobre una base ya
  sincronizada, el sistema no deberá cambiar ningún registro (ni ids, ni
  códigos, ni nombres).
- **CAT-R03.2** — Cuando se sincroniza sobre el catálogo provisional de la
  primera versión (códigos `VE-01…`), el sistema deberá sustituirlo por el
  oficial, conservando las instituciones que lo referenciaban, sin ubicación.
- **CAT-R03.3** — Cuando se sincroniza, el sistema deberá eliminar las
  materias que ya no están en el currículo y actualizar el nombre de las que
  cambiaron.

### CAT-R04 — Catálogos compartidos

- **CAT-R04.1** — El sistema deberá mostrar los mismos catálogos desde
  cualquier institución y sin institución en el contexto.
- **CAT-R04.2** — Las tablas de catálogos no deberán tener `institution_id`
  ni Row Level Security.

## Requisitos no funcionales

- **CAT-NF01** — La sincronización es segura en producción: no crea
  instituciones ni usuarios (`CatalogSeeder`).
- **CAT-NF02** — El origen geográfico es `database/seeders/data/venezuela-divisions.json`;
  los códigos internos dependen de la posición, por lo que solo se agregan
  entradas al final.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| CAT-R01.1 | `tests/Feature/GlobalCatalogsTest.php` › «the geographic catalog covers all of Venezuela» |
| CAT-R01.2 | `tests/Feature/GlobalCatalogsTest.php` › «states use their ISO 3166-2 code and current name» |
| CAT-R01.3 | `tests/Feature/GlobalCatalogsTest.php` › «a state exposes its municipalities and parishes» |
| CAT-R02.1 | `tests/Feature/GlobalCatalogsTest.php` › «the subjects catalog follows the current MPPE curriculum» |
| CAT-R03.1 | `tests/Feature/GlobalCatalogsTest.php` › «seeding the catalogs again changes nothing» |
| CAT-R03.2 | `tests/Feature/GlobalCatalogsTest.php` › «the placeholder catalog of the first version is replaced, keeping institutions» |
| CAT-R03.3 | `tests/Feature/GlobalCatalogsTest.php` › «subjects dropped from the curriculum are removed when re-seeding» |
| CAT-R04.1 | `tests/Feature/GlobalCatalogsTest.php` › «the catalogs are the same from any institution and with no institution at all» |
| CAT-R04.2 | `tests/Feature/GlobalCatalogsTest.php` › «catalog tables are not tenant-scoped» |
