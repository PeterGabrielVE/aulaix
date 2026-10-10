## Spec

<!-- Enlace a la spec y la fase: requisitos / diseño / tareas / implementación. -->
- Spec: `specs/NNN-nombre/`
- Criterios cubiertos: `XXX-R01.1`, …
- Tareas: `T-01`, …

## Qué cambia

<!-- Resumen breve, para quien revisa. -->

## Checklist

- [ ] La spec está aprobada, o este PR **es** la revisión de la spec (P-01, P-02).
- [ ] Spec actualizada si la implementación divergió (P-04).
- [ ] Cada criterio cubierto tiene test y está en la matriz de trazabilidad (P-03).
- [ ] Capas respetadas; la suite `Architecture` pasa (A-09).
- [ ] Tablas nuevas con datos de institución: `belongsToInstitution()` + `RowLevelSecurity::enable()` (S-01).
- [ ] Nada del tenant sale del input del usuario (S-02).
- [ ] Sin secretos, tokens ni PII en logs; `#[\SensitiveParameter]` donde aplica (S-07).
- [ ] Toca autenticación, tenancy o permisos → `/security-review` ejecutado (S-12).
- [ ] Migraciones con `down()` probado; migraciones de datos con plan de rollback.
- [ ] `vendor/bin/pint --dirty` sin cambios.
