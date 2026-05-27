# Fase 5 - Facturacion y comprobantes

## 1. Objetivo

Esta fase implementa comprobantes internos ligados a pagos validos del sistema. El comprobante funciona como recibo operativo del consultorio y no como factura fiscal.

No se implementan CFDI, timbrado, integracion SAT/PAC, cancelacion fiscal ni datos fiscales avanzados. El modulo nuevo consume la informacion financiera existente sin sustituir ni modificar las reglas de pagos y cortes implementadas en la Fase 3.

## 2. Estado inicial

Al iniciar la fase no existia un modulo real de facturacion ni comprobantes:

- No existian modelos llamados `Factura`, `Facturacion`, `Comprobante`, `Recibo` o `Ticket`.
- No existian controladores, requests, resources, migrations, factories o pruebas para emitir recibos.
- No existian rutas `/api/comprobantes`.
- No habia relacion persistida entre un recibo interno y un pago.
- No habia folio interno, cancelacion logica ni prevencion de doble comprobante.
- La documentacion de auditoria ya mencionaba facturacion como funcionalidad ausente.

Los pagos si contaban con integridad financiera previa: pago activo, montos liquidados y proteccion de cortes cerrados. Esa logica se reutilizo y no se modifico.

## 3. Problemas detectados

| ID | Problema | Resultado de fase |
|---|---|---|
| FAC-001 | Ausencia total de modulo de comprobantes. | Cerrado: se creo un modulo minimo de comprobantes internos. |
| FAC-002 | Falta de comprobante ligado a un pago. | Cerrado: `comprobantes.idPago` referencia a `pagos.idPago`. |
| FAC-003 | Riesgo de doble comprobante por pago. | Cerrado: existe regla de negocio y restriccion unica sobre `idPago`. |
| FAC-004 | Falta de folio unico. | Cerrado: folio generado por backend y columna unica. |
| FAC-005 | Falta de cancelacion controlada. | Cerrado: cancelacion logica mediante `estado=false`. |
| FAC-006 | Riesgo de manipular montos del recibo desde frontend. | Cerrado: campos de importe se prohiben y se copian desde el pago. |
| FAC-007 | Confusion entre recibo interno y factura fiscal real. | Delimitado: CFDI/SAT/PAC queda fuera de esta fase. |

## 4. Decisiones tecnicas

Se creo un modulo nuevo compuesto por migracion, modelo, request, resource, servicio de dominio, controlador, rutas y pruebas Feature.

### Tabla creada

La migracion `2026_05_27_000001_create_comprobantes_table.php` agrega `comprobantes` con los campos:

| Campo | Funcion |
|---|---|
| `idComprobante` | Llave primaria del recibo interno. |
| `idPago` | Pago que origina el comprobante; es foreign key y unico. |
| `folio` | Folio interno generado por backend; es unico. |
| `fechaEmision` | Momento de emision. |
| `total`, `efectivo`, `tarjeta` | Snapshot de importes tomados del pago al emitirse. |
| `estado` | `true` para emitido/activo y `false` para cancelado. |
| `observaciones` | Texto opcional no financiero. |

Se decidio guardar un snapshot de importes porque un recibo ya emitido debe conservar lo mostrado al momento de expedicion. La Fase 3 permite que un pago de un corte abierto pueda actualizarse validamente; sin snapshot, la visualizacion del comprobante cambiaria despues de emitirse. Los importes del snapshot nunca se reciben del frontend: se copian del pago validado.

### Lógica principal

`ComprobanteService` realiza emision y cancelacion dentro de `DB::transaction()`:

- Bloquea el pago con `lockForUpdate()` durante la emision.
- Reutiliza `CajaService::pagoEstaLiquidado()` para validar montos, sin duplicar reglas financieras.
- Rechaza pagos inactivos o no liquidados con `422`.
- Rechaza cualquier comprobante previo para el mismo pago, incluido uno cancelado.
- Genera folio con prefijo `CMP-`, fecha y un ULID generado en backend.
- Cancela el comprobante cambiando solo `estado=false`.

La politica elegida es un solo comprobante historico por pago. Si un comprobante se cancela, no puede reemitirse otro en esta fase; una futura funcion de sustitucion o reimpresion debera ser auditada expresamente.

### Rutas agregadas

| Metodo | Ruta | Uso |
|---|---|---|
| `GET` | `/api/comprobantes` | Listado de comprobantes activos; admite filtros simples `idPago` e `idPersona`. |
| `POST` | `/api/comprobantes` | Emision de comprobante desde un pago. |
| `GET` | `/api/comprobantes/{comprobante}` | Consulta de comprobante activo. |
| `DELETE` | `/api/comprobantes/{comprobante}` | Cancelacion logica. |

Las cuatro rutas estan dentro del grupo existente `auth:sanctum`, `empleado.activo` y `rol:admin,recepcionista`.

### Cancelacion

Un comprobante cancelado:

- Persiste en base de datos con `estado=false`.
- Ya no aparece en el listado normal ni se consulta por endpoint normal; responde `404`.
- No puede cancelarse dos veces; el segundo `DELETE` responde `404`.
- No altera el pago ni el corte asociado.

### Alcance fiscal

No se implementa factura fiscal real porque el proyecto no contiene requerimientos fiscales, certificados, RFC de receptor para comprobantes, conceptos CFDI, impuestos, timbrado ni proveedor PAC. El modulo creado es exclusivamente un comprobante interno de pago.

## 5. Reglas de negocio aplicadas

- Todo comprobante se asocia a un pago existente.
- El pago debe tener `estado=true`.
- El pago debe estar totalmente liquidado conforme a la regla financiera existente.
- Solo puede existir un comprobante historico por pago, aunque el anterior haya sido cancelado.
- El folio es unico y lo genera el backend.
- El frontend no puede enviar `folio`, `fechaEmision`, `total`, `efectivo`, `tarjeta` ni `estado`.
- Los importes mostrados por el comprobante se toman como snapshot del pago asociado al emitirlo.
- Los datos basicos de paciente y cajero se consultan desde las relaciones del pago.
- Cancelar un comprobante no elimina ni desactiva el pago.
- Cancelar un comprobante no cambia el corte.
- Se permite emitir comprobante para un pago perteneciente a un corte cerrado, porque la operacion no modifica datos financieros.
- Admin y recepcionista pueden emitir, consultar y cancelar comprobantes.
- Dentista no puede emitir, consultar ni cancelar comprobantes.
- Este modulo representa recibos internos, no facturas fiscales.

## 6. Convenciones aplicadas

- Se utilizaron nombres del dominio en espanol: `Comprobante`, `folio`, `fechaEmision`, `estado` y `observaciones`.
- La estructura de tabla se incorporo mediante una migration nueva, sin modificar migraciones historicas.
- `StoreComprobanteRequest` valida entrada y prohibe campos derivados o sensibles.
- `ComprobanteController` orquesta las respuestas HTTP y la visibilidad de activos.
- `ComprobanteService` contiene las operaciones transaccionales y reutiliza `CajaService` para liquidacion.
- `ComprobanteResource` presenta el comprobante, snapshot de importes y datos basicos relacionados sin exponer contacto adicional del paciente.
- No se modificaron usuarios/empleados, citas, servicios, pacientes, pagos, cortes, recetas ni catalogos.
- No fue necesario agregar una relacion inversa a `Pago`; la relacion `Comprobante::pago()` cubre el modulo nuevo sin alterar el modelo financiero existente.
- Se mantuvo el contrato de las rutas existentes; solo se agregaron rutas nuevas.

## 7. Archivos modificados

| Archivo | Cambio | Motivo |
|---|---|---|
| `database/migrations/2026_05_27_000001_create_comprobantes_table.php` | Creacion de tabla `comprobantes`. | Persistir recibos internos, folio, snapshot y cancelacion. |
| `app/Models/Comprobante.php` | Nuevo modelo y relacion con pago. | Representar comprobante y scope de activos. |
| `app/Http/Requests/StoreComprobanteRequest.php` | Nuevo FormRequest. | Validar `idPago` y prohibir campos derivados. |
| `app/Http/Resources/ComprobanteResource.php` | Nuevo resource JSON. | Mostrar recibo, importes congelados, paciente y cajero. |
| `app/Services/ComprobanteService.php` | Nuevo servicio de dominio. | Emitir/cancelar transaccionalmente y reutilizar liquidacion de caja. |
| `app/Http/Controllers/ComprobanteController.php` | Nuevo controlador API. | Listar, emitir, mostrar y cancelar comprobantes. |
| `routes/api.php` | Cuatro rutas nuevas de comprobantes. | Exponer API bajo permisos existentes de caja. |
| `tests/Feature/ComprobanteTest.php` | Nueva suite Feature. | Cubrir reglas, permisos y regresiones propias del modulo. |
| `docs/FASE_5_FACTURACION.md` | Documentacion de fase. | Registrar decisiones, pruebas y pendientes. |

## 8. Pruebas agregadas o modificadas

| Archivo de prueba | Prueba | Que valida | Resultado |
|---|---|---|---|
| `tests/Feature/ComprobanteTest.php` | Emision por admin y recepcionista | Un pago valido produce `201`, folio y asociacion. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Permisos de dentista y usuario anonimo | Respuestas `403` y `401` segun corresponda. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Pago inexistente, inactivo o no liquidado | La emision se rechaza con `422`. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Doble comprobante y comprobante cancelado | Solo existe un comprobante historico por pago. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Folios para pagos distintos | Los folios generados son diferentes. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Campos enviados por frontend | Folio, fecha, montos y estado se rechazan con `422`. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Snapshot tras editar un pago abierto | El comprobante conserva importes de su emision. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Listado y consulta | Retorna comprobantes activos con pago y paciente. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Cancelacion logica | No modifica pago ni corte; no lista cancelados ni permite doble cancelacion. | Pasa |
| `tests/Feature/ComprobanteTest.php` | Pago en corte cerrado | Se permite emitir comprobante sin alterar el cierre. | Pasa |

## 9. Comandos ejecutados

Las pruebas se ejecutaron contra `dentista_db_testing` mediante `APP_ENV=testing`, `DB_CONNECTION=mysql` y `DB_DATABASE=dentista_db_testing`. La base principal no fue utilizada.

| Comando | Resultado | Observaciones |
|---|---|---|
| `rg -n -i "factura|facturacion|facturación|comprobante|recibo|ticket" app database routes tests docs` | Correcto | Confirmo ausencia de modulo; solo se encontro referencia documental previa. |
| `php -l` sobre modelo, controlador, request, resource, servicio, migration y prueba nuevos | Correcto | Sin errores de sintaxis. |
| `php artisan config:clear` | Correcto | Ejecutado con base de testing. |
| `php artisan cache:clear` | Correcto | Ejecutado con base de testing. |
| `php artisan migrate:fresh --seed --database=mysql --force` | Correcto | Creo la tabla nueva sobre `dentista_db_testing`. |
| `php artisan migrate:status --database=mysql` | Correcto | Reporto 18 migraciones aplicadas, incluida `create_comprobantes_table`. |
| `php artisan test --filter=ComprobanteTest` | Correcto: 16 pruebas, 74 aserciones | Cobertura funcional del modulo nuevo. |
| `php artisan route:list --path=api/comprobantes -v` | Correcto: 4 rutas | Confirma autenticacion, empleado activo y rol admin/recepcionista. |
| `php artisan test --filter=PagoTest` | Correcto: 20 pruebas, 62 aserciones | Regresion de integridad financiera. |
| `php artisan test --filter=CorteTest` | Correcto: 12 pruebas, 30 aserciones | Regresion de cortes. |
| `php artisan test --filter=PersonaTest` | Correcto: 16 pruebas, 47 aserciones | Regresion de pacientes. |
| `php artisan test` | Correcto: 118 pruebas, 327 aserciones | Suite completa verde; duracion aproximada 5.25 s. |
| `git diff --check` | Correcto | Sin errores de espacios en el diff. |

Detalle del reinicio de datos:

| Dato | Valor |
|---|---|
| Comando ejecutado | `php artisan migrate:fresh --seed --database=mysql --force` |
| Base usada | `dentista_db_testing` |
| Motivo | Validar la migration nueva y ejecutar pruebas reproducibles del modulo. |
| Resultado | Las 18 migraciones y seeders se ejecutaron correctamente. |
| Datos afectados | Solo datos temporales de testing; no se afecto `dentista_db`. |

## 10. Resultado final

- `php artisan test --filter=ComprobanteTest` paso: 16 pruebas y 74 aserciones.
- `php artisan test --filter=PagoTest` paso: 20 pruebas y 62 aserciones.
- `php artisan test --filter=CorteTest` paso: 12 pruebas y 30 aserciones.
- `php artisan test --filter=PersonaTest` paso: 16 pruebas y 47 aserciones.
- `php artisan test` paso completo: 118 pruebas y 327 aserciones.
- FAC-001 quedo cerrado: existe un modulo minimo de comprobantes internos.
- FAC-002 quedo cerrado: todo comprobante nuevo queda asociado a un pago validado.
- La Fase 3 no fue alterada: el modulo consulta el pago y reutiliza su regla de liquidacion, sin cambiar pagos ni cortes.

## 11. Riesgos o pendientes

- Facturacion fiscal real mediante CFDI, SAT/PAC, certificados, impuestos y cancelacion fiscal no esta implementada.
- No se genera archivo PDF imprimible ni representacion grafica del recibo.
- No existe envio de comprobantes por correo o mensajeria.
- No existe flujo de reimpresion o sustitucion auditada; la politica actual impide reemitir tras cancelacion.
- No existe reporte agregado de comprobantes emitidos/cancelados para conciliacion administrativa.
- El folio interno es tecnico y unico; si se requiere una serie consecutiva fiscal o administrativa, debe disenarse con reglas de concurrencia y auditoria propias.

## 12. Notas para el siguiente desarrollador

- La emision y cancelacion se implementan en `app/Services/ComprobanteService.php`.
- La estructura persistida y sus restricciones se encuentran en la migration de `comprobantes`.
- `ComprobanteResource` presenta importes del snapshot; no debe reemplazarse por montos enviados desde cliente.
- `ComprobanteTest` protege permisos, pagos validos, duplicidad, cancelacion, snapshot y corte cerrado.
- El frontend debe enviar solamente `idPago` y, opcionalmente, `observaciones` al emitir.
- El frontend debe interpretar `DELETE` exitoso como cancelacion logica; un comprobante cancelado no aparece en el listado normal.
- Cualquier futura factura fiscal debe tratarse como modulo distinto o ampliacion formal, sin confundir este recibo interno con un CFDI.
