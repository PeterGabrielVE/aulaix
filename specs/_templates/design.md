---
spec: NNN-nombre
estado: borrador
fecha: AAAA-MM-DD
requisitos: requirements.md
---

# Diseño — <Nombre de la funcionalidad>

## Resumen

Dos o tres frases: cómo se resuelve y qué módulo(s) toca.

## Módulo y capas

```
src/<Módulo>/
├── Domain/
├── Application/
└── Infrastructure/
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `…` | Agregado | … |
| `…` | Value Object | … |
| `…` | Servicio de dominio | … |
| `…` | Evento de dominio | … |

## Casos de uso (Application)

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `…Handler` | `…Command` | `…Id` / DTO | XXX-R01.1 |

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `…Repository` | Domain | `Eloquent…Repository` | … |

## Adaptadores de entrada (`app/`)

Rutas, controladores, FormRequests y middleware, y a qué caso de uso llama
cada uno.

## Datos

Migraciones, índices, restricciones. **Toda tabla con datos de una
institución:** `belongsToInstitution()` + `RowLevelSecurity::enable()` (S-01).

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| … | … |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | … | … | test / regla |
| **T**ampering | … | … | … |
| **R**epudiation | … | … | … |
| **I**nformation disclosure | … | … | … |
| **D**enial of service | … | … | … |
| **E**levation of privilege | … | … | … |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `…` | 422 | … |

## Estrategia de pruebas

- **Dominio (unit):** …
- **Aplicación (unit, adaptadores en memoria):** …
- **Infraestructura (integración, Postgres):** …
- **Feature (HTTP):** …

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| … | … | … |

## Riesgos y plan de rollback

…
