# Fase 3 - Caja, pagos y cortes

## 1. Objetivo

Esta fase corrige la integridad financiera de pagos y cortes de caja. El backend ahora evita registrar pagos incompletos, excedidos o con montos invalidos; deriva los datos sensibles que no deben depender del frontend; y protege los cortes cerrados y sus pagos contra modificaciones posteriores.

El alcance se limita a caja, pagos y cortes. Se conservaron las protecciones de autenticacion y roles implementadas anteriormente, asi como el comportamiento de agenda y citas.

## 2. Estado inicial

Antes de esta fase:

- `PagoController` asociaba el pago al empleado autenticado y al primer corte abierto encontrado, pero no usaba transaccion.
- El alta de pago aceptaba `total`, `efectivo` y `tarjeta` con valores no negativos, sin exigir que la suma liquidara el total.
- `pagado` se guardaba como `true` al crear el pago, incluso si los montos no representaban una liquidacion real.
- `UpdatePagoRequest` permitia enviar `idCorte` y `pagado`, y la actualizacion no bloqueaba pagos de cortes ya cerrados.
- `CorteController` calculaba totales al primer cierre, pero un corte cerrado aun podia modificarse posteriormente.
- El cliente podia intentar enviar totales de corte al actualizar.
- La baja logica de un pago no verificaba si pertenecia a un corte cerrado.
- No habia una politica implementada de pagos parciales; el comportamiento existente dejaba estados financieros ambiguos.

## 3. Problemas detectados

| ID | Problema | Estado al cierre de fase |
|---|---|---|
| FIN-001 | Pagos incompletos aceptados | Corregido: se rechazan con `422`. |
| FIN-002 | Pagos excedidos aceptados | Corregido: se rechazan con `422`. |
| FIN-003 | Total cero, negativo o montos negativos | Corregido mediante validacion y pruebas. |
| FIN-004 | `pagado` incoherente o manipulable | Corregido: el backend lo deriva y el campo del cliente se prohibe. |
| FIN-005 | `idEmpleado` o `idCorte` manipulables | Corregido al crear: se prohiben y se derivan en backend. En update no se permite mover el pago. |
| FIN-006 | Corte cerrado modificable | Corregido: actualizacion y baja logica de corte cerrado devuelven `422`. |
| FIN-007 | Pago de corte cerrado editable, movible o desactivable | Corregido: las tres operaciones se bloquean con `422`. |
| FIN-008 | Falta de transacciones financieras | Corregido para registro/actualizacion/baja de pago y apertura/actualizacion/cierre/baja de corte. |
| FIN-009 | Falta de politica de pagos parciales | Definido: en esta fase no se admiten pagos parciales. |

## 4. Decisiones tecnicas

La logica financiera compartida se concentro en `app/Services/CajaService.php`. Los `FormRequest` siguen siendo responsables de la forma y validacion de entrada, mientras los controladores solo coordinan la respuesta HTTP y delegan las operaciones financieras transaccionales al servicio.

La consistencia de montos se valida convirtiendo texto decimal a centavos enteros. El servicio admite hasta dos decimales y compara:

```text
totalCentavos = efectivoCentavos + tarjetaCentavos
```

Esta estrategia evita depender de comparaciones con `float` en la regla critica. Es consistente con los campos `DECIMAL(8,2)` presentes en las tablas de pagos y cortes.

Las decisiones aplicadas son:

- `StorePagoRequest` exige `total > 0`, `efectivo >= 0`, `tarjeta >= 0` y pago totalmente liquidado.
- `UpdatePagoRequest` vuelve a validar la liquidacion cuando se cambian montos, tomando los valores existentes para campos no enviados.
- `pagado`, `idEmpleado` e `idCorte` estan prohibidos en el alta desde el cliente; si se envian, la API responde `422`.
- `pagado` se persiste como `true` solo para pagos aceptados que ya pasaron la regla de liquidacion completa.
- `PagoResource` deriva `pendiente` y `pagado` de los montos almacenados para no exponer combinaciones contradictorias en registros historicos.
- `idEmpleado` se obtiene del empleado autenticado.
- `idCorte` se obtiene del unico corte activo abierto encontrado por el backend.
- Un corte abierto se identifica con `estado=true` y `fechaFin=null`.
- Un pago no puede actualizarse ni darse de baja si su corte ya tiene `fechaFin`.
- Un corte cerrado no puede actualizarse ni darse de baja.
- El cierre calcula `tEfectivo` y `tTarjeta` exclusivamente desde pagos activos asociados al corte.
- Los totales enviados manualmente al cerrar un corte se rechazan con `422`, en lugar de usarlos o ignorarlos silenciosamente.
- Las operaciones financieras compuestas se ejecutan con `DB::transaction()` y bloqueo de filas relevantes mediante `lockForUpdate()`.

Los conflictos de regla financiera se expresan como errores `422`, ya sea desde validacion de request o mediante `ValidationException`.

## 5. Reglas de negocio aplicadas

- No existen pagos parciales en esta fase: un pago se acepta unicamente si esta liquidado al registrarse o actualizarse.
- `total` debe ser mayor que cero.
- `efectivo` y `tarjeta` deben ser mayores o iguales que cero.
- `efectivo + tarjeta` debe ser exactamente igual a `total`.
- Un pago aceptado queda con `pagado=true` y `pendiente=0`.
- El frontend no controla `pagado`, `idEmpleado` ni `idCorte` al crear pagos.
- Un pago requiere un corte activo abierto; sin corte, la API devuelve `422`.
- Un pago no puede cambiar de corte mediante update.
- Los pagos de un corte cerrado no pueden editarse ni desactivarse.
- Solo puede abrirse un corte si no existe otro corte activo abierto.
- Al cerrar un corte, los totales se calculan desde sus pagos activos.
- Se permite cerrar un corte sin pagos; sus totales quedan en cero.
- Los cortes cerrados son inmutables dentro del alcance actual.
- El cierre y las operaciones financieras relevantes son transaccionales.

## 6. Convenciones aplicadas

- Se mantuvieron los nombres del dominio en espanol: `Pago`, `Corte`, `CajaService`, `pagado`, `idEmpleado`, `idCorte`, `tEfectivo` y `tTarjeta`.
- Las validaciones de entrada quedaron en `StorePagoRequest`, `UpdatePagoRequest`, `StoreCorteRequest` y `UpdateCorteRequest`.
- Los controladores conservan la orquestacion HTTP y las respuestas existentes; las reglas reutilizables quedaron en un servicio de dominio.
- Los `Resources` mantienen el contrato actual y corrigen solamente la coherencia de datos financieros expuestos.
- Se utilizo `DB::transaction()` para operaciones que pueden alterar consistencia financiera.
- No se modificaron rutas ni middleware; las rutas verificadas conservan `auth:sanctum`, `empleado.activo` y `rol:*`.
- No se modificaron modulos de personas, servicios, empleados, citas, recetas ni catalogos como parte de esta fase.
- Las modificaciones de citas existentes en el arbol de trabajo pertenecen a la Fase 2 previa y no fueron alteradas durante esta implementacion.

## 7. Archivos modificados

| Archivo | Cambio | Motivo |
|---|---|---|
| `app/Services/CajaService.php` | Creacion de servicio de dominio | Centralizar liquidacion, corte abierto/cerrado y transacciones financieras. |
| `app/Http/Controllers/PagoController.php` | Delegacion al servicio | Registrar, actualizar y desactivar pagos con consistencia transaccional. |
| `app/Http/Controllers/CorteController.php` | Delegacion al servicio | Abrir, cerrar y proteger cortes cerrados. |
| `app/Http/Requests/StorePagoRequest.php` | Validaciones financieras y campos prohibidos | Rechazar montos inconsistentes y datos controlados por backend. |
| `app/Http/Requests/UpdatePagoRequest.php` | Validacion de liquidacion en edicion | Evitar inconsistencias y movimiento de corte. |
| `app/Http/Requests/StoreCorteRequest.php` | Campos derivados prohibidos | Evitar que el frontend controle estado o totales iniciales. |
| `app/Http/Requests/UpdateCorteRequest.php` | Totales/estado prohibidos y fecha valida | Cerrar cortes sin confiar en montos del cliente. |
| `app/Http/Resources/PagoResource.php` | Derivacion coherente de `pendiente` y `pagado` | Evitar mostrar estados contradictorios. |
| `database/factories/PagoFactory.php` | Montos liquidados consistentes | Generar escenarios de prueba financieros validos. |
| `tests/Feature/PagoTest.php` | Cobertura ampliada de pagos | Verificar montos, campos derivados, permisos e inmutabilidad. |
| `tests/Feature/CorteTest.php` | Cobertura ampliada de cortes | Verificar apertura, cierre, totales e inmutabilidad. |
| `docs/FASE_3_CAJA_PAGOS_CORTES.md` | Documentacion de fase | Registrar decisiones, evidencia y pendientes. |

## 8. Pruebas agregadas o modificadas

| Archivo de prueba | Prueba o grupo | Que valida | Resultado |
|---|---|---|---|
| `tests/Feature/PagoTest.php` | Pagos en efectivo, tarjeta, mixto y decimales | Pagos completamente liquidados devuelven `201`, incluyendo `500.50 = 300.25 + 200.25`. | Pasa |
| `tests/Feature/PagoTest.php` | Pagos incompletos, excedidos, cero y negativos | Montos invalidos se rechazan con `422`. | Pasa |
| `tests/Feature/PagoTest.php` | Campos derivados en alta | `idEmpleado`, `idCorte` y `pagado` no pueden ser impuestos por frontend. | Pasa |
| `tests/Feature/PagoTest.php` | Alta sin corte activo | No se registra pago y se devuelve `422`. | Pasa |
| `tests/Feature/PagoTest.php` | Actualizacion de pago abierto | Permite montos validos y rechaza montos inconsistentes o cambio de corte. | Pasa |
| `tests/Feature/PagoTest.php` | Pago de corte cerrado | No permite editar, mover ni desactivar el pago. | Pasa |
| `tests/Feature/PagoTest.php` | Permisos | Admin y recepcionista operan pagos segun rutas actuales; dentista recibe `403`. | Pasa |
| `tests/Feature/CorteTest.php` | Apertura y corte activo | Permite un corte abierto y rechaza un segundo corte activo. | Pasa |
| `tests/Feature/CorteTest.php` | Cierre con pagos activos | Calcula `tEfectivo=400` y `tTarjeta=250` desde pagos. | Pasa |
| `tests/Feature/CorteTest.php` | Cierre sin pagos | Permite el cierre y persiste totales en cero. | Pasa |
| `tests/Feature/CorteTest.php` | Datos manuales y segundo cierre | Rechaza totales enviados por cliente y no permite cerrar dos veces. | Pasa |
| `tests/Feature/CorteTest.php` | Corte cerrado | No permite modificarlo ni desactivarlo. | Pasa |
| `tests/Feature/CorteTest.php` | Permisos | Dentista no puede operar cortes. | Pasa |

## 9. Comandos ejecutados

La base utilizada para ejecutar pruebas fue `dentista_db_testing`, configurada por variables de entorno durante los comandos. Se reinicio solamente esa base de testing; no se uso ni se altero `dentista_db`.

| Comando | Resultado | Observaciones |
|---|---|---|
| `php -l` sobre servicio, controladores, requests, resource, factory y tests de Fase 3 | Correcto | Sin errores de sintaxis PHP. |
| `php artisan test --filter=PagoTest` | Correcto: 20 pruebas, 62 aserciones | Ejecutado contra `dentista_db_testing`. |
| `php artisan test --filter=CorteTest` | Correcto: 12 pruebas, 30 aserciones | Ejecutado contra `dentista_db_testing`. |
| `php artisan config:clear` | Correcto | Con `APP_ENV=testing`, `DB_DATABASE=dentista_db_testing`. |
| `php artisan cache:clear` | Correcto | Limpieza previa a verificacion completa. |
| `php artisan migrate:fresh --seed --database=mysql --force` | Correcto | Reinicio destructivo solo de `dentista_db_testing`; reconstruyo migraciones y seeders para prueba reproducible. |
| `php artisan migrate:status --database=mysql` | Correcto | Las 17 migraciones reportadas se encuentran aplicadas en testing. |
| `php artisan test` | Correcto: 94 pruebas, 226 aserciones | Suite completa verde; duracion aproximada 4.18 s. |
| `php artisan route:list --path=api -v` | Correcto: 51 rutas | Pagos y cortes conservan `auth:sanctum`, `empleado.activo` y permisos de rol. |
| `git diff --check` | Correcto | No se detectaron errores de espacios introducidos por los cambios. |

Detalle del reinicio de datos:

| Dato | Valor |
|---|---|
| Comando | `php artisan migrate:fresh --seed --database=mysql --force` |
| Base usada | `dentista_db_testing` |
| Motivo | Ejecutar una verificacion reproducible de la integridad financiera. |
| Resultado | Migraciones y seeders ejecutados correctamente; suite completa verde. |
| Datos afectados | Solo datos temporales de testing; la base principal no fue utilizada. |

## 10. Resultado final

- `php artisan test --filter=PagoTest` paso: 20 pruebas y 62 aserciones.
- `php artisan test --filter=CorteTest` paso: 12 pruebas y 30 aserciones.
- `php artisan test` paso completo: 94 pruebas y 226 aserciones.
- FIN-001 y FIN-002 quedaron cerrados: un pago incompleto o excedido devuelve `422`.
- La integridad financiera principal queda cubierta por pruebas automatizadas para registro y actualizacion de pagos, cierre de cortes, inmutabilidad posterior y permisos existentes.
- Las fases anteriores permanecen verificadas por la suite: autenticacion/tokens y citas siguen en verde.

## 11. Riesgos o pendientes

- No existe un flujo de ajustes auditados, cancelaciones financieras o devoluciones para corregir un corte cerrado; por decision de esta fase, un corte cerrado es inmutable.
- No se implementaron pagos parciales; cualquier requerimiento futuro para abonos necesitara definir saldos, estados y conciliacion.
- La transaccion y los bloqueos reducen riesgos de concurrencia, pero no existe una restriccion unica de base de datos que garantice por si sola un unico corte activo abierto ante aperturas simultaneas extremas.
- No se implemento bitacora financiera inmutable ni reporte de conciliacion; son necesidades futuras de auditoria operativa.

## 12. Notas para el siguiente desarrollador

- La regla principal de liquidacion, apertura/cierre y proteccion de movimientos esta en `app/Services/CajaService.php`.
- La superficie de entrada del frontend se controla en los cuatro `FormRequest` de pagos y cortes.
- `PagoResource` deriva `pendiente` y `pagado`; no se debe volver a confiar en un valor `pagado` enviado por cliente.
- El frontend debe enviar para un pago solamente los datos permitidos, incluyendo montos que liquiden exactamente el total; el backend asigna empleado y corte.
- Para cerrar un corte, el frontend puede enviar la accion/fecha admitida, pero no debe enviar totales calculados.
- Las pruebas en `PagoTest` y `CorteTest` protegen las reglas financieras e inmutabilidad; cualquier cambio futuro de pagos parciales, devoluciones o ajustes auditados debe actualizar explicitamente esas reglas y pruebas.
- Las rutas deben conservar `auth:sanctum` y `empleado.activo`, ademas de los permisos de rol vigentes.
