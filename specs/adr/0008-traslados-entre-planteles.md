---
estado: aceptada
fecha: 2026-10-21
fecha-aceptación: 2026-10-21
spec: 009-estudiantes
---

# ADR 0008 — Traslados entre planteles: visibilidad cruzada por la RLS y copia en una sola transacción

## Contexto

El traslado de SPEC-009 (EST-R07) es la primera funcionalidad en la que dos
planteles comparten datos. Choca con dos garantías del sistema:

1. **La RLS aísla cada plantel (S-01, ADR 0003).** El plantel destino no
   puede ver los estudiantes del origen. Por eso no sabe a qué plantel
   enviar la solicitud, y la respuesta no debe revelar si el estudiante
   existe (EST-R07.2).
2. **Cada petición trabaja para un solo plantel.** Cuando el origen aprueba,
   la ficha debe crearse en el destino (EST-R07.5), pero la RLS del origen
   prohíbe escribir filas de otro plantel.

Además, el retiro en el origen y el alta en el destino deben ocurrir juntos:
un estudiante no puede quedar retirado del origen sin llegar al destino.

## Decisión

### A. La solicitud se dirige a una identificación, no a un plantel

`transfer_requests` guarda el plantel destino (`institution_id`), la
identificación pedida y su estado. **No guarda el plantel de origen al
crearse.** Su RLS tiene políticas propias:

| Comando | Política |
|---------|----------|
| `SELECT` | Fila del plantel activo (destino), **o** respondida por el plantel activo (`origin_institution_id`), **o** pendiente y con un estudiante **activo** del plantel activo con esa identificación. |
| `INSERT` | Solo como destino (`institution_id` = plantel activo), pendiente y sin origen. *(Origen y estado añadidos por SPEC-015.)* |
| `UPDATE` | Las mismas filas que `SELECT`. `WITH CHECK`: destino u origen = plantel activo. Además, el *trigger* `guard_origin` (SPEC-015 RLT-R05): el destino y la identificación no cambian, y el origen solo lo fija, una vez y mientras está pendiente, el plantel que responde. |
| `DELETE` | Nunca (se conservan como historial). |

La tercera condición es una subconsulta a `students`, que a su vez está
filtrada por **su** RLS. Por eso solo ve la solicitud el plantel donde ese
estudiante está activo. Ningún otro plantel la ve, ni con SQL directo. El
destino no aprende nada hasta la respuesta, y una identificación inexistente
se comporta igual: queda pendiente y caduca (EST-R07.2, R07.7).

### B. La aprobación copia la ficha en la misma transacción, cambiando de plantel

Al aprobar, el caso de uso, dentro de **una** transacción:

1. marca la solicitud como aprobada y retira al estudiante en el origen
   (motivo «cambio de plantel»), con su bitácora;
2. activa el plantel destino (`TenantActivator`: variable de la RLS y
   *team* de permisos);
3. crea en el destino el estudiante, sus representantes y sus vínculos, con
   la bitácora del destino;
4. reactiva el plantel origen (en un `finally`).

Si algo falla, la transacción deshace todo: ni retiro ni alta.

El cambio de plantel ocurre **solo** en un adaptador de `Students`
(`TransferCopier`). Un test de arquitectura impide usar `TenantActivator`
en otras clases de `Students`.

## Consecuencias

**Positivas**
- El aislamiento sigue en la base de datos. Ningún plantel ve solicitudes o
  fichas ajenas, salvo la que va dirigida a uno de sus estudiantes activos.
- Atómico: no hay estados intermedios («aprobado pero sin copiar»), ni
  colas, ni tareas de reintento.
- La caducidad se calcula al leer (15 días desde la solicitud): no hace
  falta una tarea programada.

**Negativas**
- La política de `SELECT` hace una subconsulta a `students`. Cuesta una
  búsqueda por índice (`institution_id`, `identification`) por solicitud
  pendiente.
- Durante el paso 3, el proceso trabaja como el plantel destino. Si un
  error de código escribiera ahí algo del origen, la RLS no lo impediría.
  Lo acota el código mínimo de `TransferCopier` y su test.
- Si el destino registró al estudiante a mano después de pedirlo, la
  aprobación falla por el índice único: el origen recibe un mensaje y debe
  rechazar la solicitud.

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| Índice de plataforma (sin RLS) de identificaciones → plantel | Una tabla legible sin plantel activo con la ubicación de todos los estudiantes (menores) de la plataforma. Rompe S-01 justo para los datos más sensibles. |
| Lectura central de `students` (`enableWithCentralReads`, como `users` en ADR 0006) | Sin plantel activo se verían todas las fichas, con datos de salud. El riesgo de ADR 0006 se aceptó para cuentas, no para menores. |
| Instantánea de la ficha en la solicitud, importada después por el destino (cola o botón) | Estados intermedios: el estudiante queda retirado en el origen y sin alta en el destino si la importación falla. EST-R07.5 pide crearlo al aprobar. Además, la instantánea con datos de salud viviría en una tabla compartida. |
| El origen aprueba primero y genera un código que el destino canjea | Era la alternativa de producto descartada en los requisitos: el destino es quien pide (EST-R07.1). |
