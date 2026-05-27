# Fase 6 - Inventario

## 1. Objetivo

Esta fase implementa un modulo minimo de inventario para administrar productos o insumos del consultorio y registrar movimientos de existencias. El alcance incluye productos activos, baja logica, control de stock, movimientos de entrada, salida y ajuste, y trazabilidad del empleado que realiza cada operacion.

La fase no integra inventario con citas, servicios, ventas, compras, pagos ni comprobantes. Es la base operativa para controlar insumos de forma independiente y auditable.

## 2. Estado inicial

Al iniciar la fase no existia un modulo real de inventario:

- No habia modelos de inventario, producto, insumo, stock o movimientos.
- No habia migraciones ni tablas para existencias.
- No habia control de stock ni historial de entradas/salidas.
- No habia rutas API de inventario.
- No habia pruebas automatizadas para inventario.
- La auditoria previa ya registraba inventario como funcionalidad ausente.

Los modulos de autenticacion, agenda, caja, pacientes y comprobantes ya se encontraban en el arbol de trabajo y se tomaron solamente como regresiones, sin modificar su logica.

## 3. Problemas detectados

| ID | Problema | Resultado de fase |
|---|---|---|
| INV-001 | Ausencia de modulo de inventario. | Cerrado: se creo API minima de productos y movimientos. |
| INV-002 | Falta de control de existencias. | Cerrado: `stockActual` se actualiza solo por movimientos controlados. |
| INV-003 | Falta de historial de movimientos. | Cerrado: cada alta/entrada/salida/ajuste genera un registro. |
| INV-004 | Riesgo de stock negativo. | Cerrado: las salidas insuficientes se rechazan con `422`. |
| INV-005 | Falta de permisos definidos. | Cerrado: admin y recepcionista gestionan; dentista recibe `403`. |
| INV-006 | Falta de baja logica. | Cerrado: productos se desactivan con `estado=false` y no reciben nuevos movimientos. |
| INV-007 | Falta de pruebas. | Cerrado: se creo `InventarioTest` con comportamiento y permisos. |

## 4. Decisiones tecnicas

Se creo un modulo nuevo independiente, compuesto por dos tablas, dos modelos, tres requests, dos resources, dos controladores, un servicio de dominio, una factory de producto, rutas API y pruebas Feature.

### Tablas creadas

`productos_inventario` almacena:

| Campo | Uso |
|---|---|
| `idProductoInventario` | Llave primaria. |
| `nombre`, `descripcion`, `unidadMedida` | Datos generales del insumo. |
| `stockActual`, `stockMinimo` | Existencia y limite operativo. |
| `costoUnitario` | Referencia opcional sin alcance contable. |
| `estado` | Disponibilidad del producto en endpoints normales. |

`movimientos_inventario` almacena:

| Campo | Uso |
|---|---|
| `idMovimientoInventario` | Llave primaria del historial. |
| `idProductoInventario` | Producto afectado. |
| `idEmpleado` | Empleado autenticado que registra el movimiento. |
| `tipoMovimiento` | `entrada`, `salida` o `ajuste`. |
| `cantidad` | Cantidad capturada; para ajuste representa el stock fisico contado. |
| `stockAnterior`, `stockNuevo` | Valores derivados por backend. |
| `motivo`, `fechaMovimiento` | Contexto y momento de la operacion. |

Las cantidades utilizan `DECIMAL(10,2)` para permitir piezas, cajas y unidades fraccionarias como mililitros o materiales medibles. En `InventarioService`, los calculos convierten valores a centesimas enteras antes de sumar o restar; asi se evita depender de comparaciones de punto flotante para la regla de stock.

La migration de movimientos inicialmente intento crear un indice con el nombre automatico de Laravel, que excedia el limite de identificador de MySQL. Se corrigio dentro de la misma migration nueva usando los nombres explicitos `mov_inv_producto_fecha_idx` y `mov_inv_tipo_idx`, y la migracion paso correctamente al repetir la reconstruccion de testing.

### Logica del servicio

`InventarioService` concentra las reglas de stock:

- Crear un producto se ejecuta en `DB::transaction()`.
- El frontend envia `stockInicial`, no `stockActual`.
- Al crear un producto, el backend almacena su stock y crea un movimiento inicial `ajuste` desde `0.00` hasta `stockInicial`, incluso cuando sea cero; asi toda existencia inicial queda auditada.
- Registrar movimiento usa `DB::transaction()` y `lockForUpdate()` sobre el producto activo.
- Una `entrada` suma cantidad al stock.
- Una `salida` resta cantidad y falla con `422` si el resultado seria negativo.
- Un `ajuste` establece `stockNuevo` igual a la cantidad fisica capturada.
- `idEmpleado`, `stockAnterior`, `stockNuevo` y `fechaMovimiento` los deriva el backend.
- Se bloquean cantidades con mas de dos decimales o negativas.

Los nombres se normalizan con `trim()` y se valida duplicidad entre productos activos sin distinguir mayusculas/minusculas. Esta validacion es de aplicacion; no se agrego restriccion parcial de base de datos porque MySQL no proporciona directamente un indice unico condicional por `estado` en este esquema.

### Baja logica e historial

La baja de un producto cambia `estado=false` y responde `204`. Un producto inactivo responde `404` al consultar, actualizar, volver a eliminar o intentar registrar nuevos movimientos. Sus movimientos historicos permanecen consultables en `/api/inventario/movimientos`.

### Factory

Se creo `ProductoInventarioFactory` para construir escenarios de prueba. No se creo factory de movimientos intencionalmente: los movimientos son evidencia de variaciones de stock y deben generarse mediante `InventarioService` o el endpoint correspondiente, evitando producir historiales desacoplados de la existencia real.

### Rutas agregadas

| Metodo | Ruta | Uso |
|---|---|---|
| `GET` | `/api/inventario/productos` | Lista productos activos. |
| `POST` | `/api/inventario/productos` | Crea producto y movimiento inicial. |
| `GET` | `/api/inventario/productos/{producto}` | Consulta producto activo. |
| `PUT/PATCH` | `/api/inventario/productos/{producto}` | Actualiza datos generales, nunca stock directo. |
| `DELETE` | `/api/inventario/productos/{producto}` | Baja logica. |
| `GET` | `/api/inventario/movimientos` | Consulta movimientos historicos, con filtro opcional por producto. |
| `POST` | `/api/inventario/movimientos` | Registra entrada, salida o ajuste. |

Las rutas estan protegidas con `auth:sanctum`, `empleado.activo` y `rol:admin,recepcionista`.

No se integro inventario con servicios, citas o ventas porque no existe una regla de consumo clinico, compra o venta definida en el proyecto; hacerlo en esta fase mezclaria dominios sin contrato funcional.

## 5. Reglas de negocio aplicadas

- Un producto representa un insumo o material administrable.
- Un producto requiere `nombre`, `unidadMedida` y `stockInicial` no negativo.
- `stockMinimo` y `costoUnitario`, si se envian, no pueden ser negativos.
- Se admiten cantidades de hasta dos decimales.
- Un producto nuevo queda activo.
- El listado y consulta normal muestran solo productos activos.
- Un nombre no puede duplicarse entre productos activos, ignorando espacios laterales y mayusculas.
- La baja de producto es logica y no elimina movimientos.
- El stock no se modifica directamente mediante update; se cambia con movimientos.
- La existencia inicial se registra como movimiento `ajuste`.
- Una entrada aumenta stock y una salida lo disminuye.
- Una salida no puede producir stock negativo.
- Un ajuste establece el stock fisico contado.
- Productos inactivos no reciben nuevos movimientos.
- Los movimientos historicos de productos inactivos siguen consultables.
- El empleado autenticado se asigna como autor del movimiento.
- El frontend no controla `stockActual`, `stockAnterior`, `stockNuevo`, `idEmpleado` ni `fechaMovimiento`.
- El resource de producto expone `bajoStock=true` cuando `stockActual <= stockMinimo`.
- Admin y recepcionista gestionan inventario; dentista no tiene acceso en esta fase.
- No se implementaron compras, ventas ni consumo automatico por servicio.

## 6. Convenciones aplicadas

- Se usaron nombres de dominio en espanol: `ProductoInventario`, `MovimientoInventario`, `InventarioService`, `stockActual`, `stockMinimo` y `tipoMovimiento`.
- Las tablas nuevas se incorporaron mediante migrations independientes sin alterar estructuras historicas.
- Los `FormRequest` validan la entrada y prohiben campos derivados.
- Los controladores orquestan HTTP y delegan reglas de stock al servicio.
- Los resources conservan una salida JSON clara y no exponen datos sensibles del empleado.
- El servicio usa transacciones y bloqueos para cambios de stock.
- No se modificaron modelos ni controladores de empleados, citas, servicios, pacientes, pagos, cortes ni comprobantes.
- `routes/api.php` se modifico solo para agregar las siete rutas del nuevo modulo dentro del grupo de permisos existente.
- La compatibilidad de fases anteriores se valido con sus pruebas existentes y la suite completa.

## 7. Archivos modificados

| Archivo | Cambio | Motivo |
|---|---|---|
| `database/migrations/2026_05_27_000002_create_productos_inventario_table.php` | Nueva tabla de productos. | Persistir insumos y existencias actuales. |
| `database/migrations/2026_05_27_000003_create_movimientos_inventario_table.php` | Nueva tabla de movimientos e indices. | Mantener historial y trazabilidad. |
| `app/Models/ProductoInventario.php` | Nuevo modelo. | Relaciones, casts y scope de activos. |
| `app/Models/MovimientoInventario.php` | Nuevo modelo. | Relacionar movimientos con producto y empleado. |
| `database/factories/ProductoInventarioFactory.php` | Nueva factory. | Datos consistentes para pruebas. |
| `app/Http/Requests/StoreProductoInventarioRequest.php` | Nuevo FormRequest. | Validar altas y prohibir stock derivado. |
| `app/Http/Requests/UpdateProductoInventarioRequest.php` | Nuevo FormRequest. | Editar datos generales sin alterar stock. |
| `app/Http/Requests/StoreMovimientoInventarioRequest.php` | Nuevo FormRequest. | Validar tipos/cantidades y campos derivados. |
| `app/Http/Resources/ProductoInventarioResource.php` | Nuevo resource. | Exponer producto y alerta `bajoStock`. |
| `app/Http/Resources/MovimientoInventarioResource.php` | Nuevo resource. | Exponer movimiento, producto y autor. |
| `app/Services/InventarioService.php` | Nuevo servicio. | Control transaccional de existencias. |
| `app/Http/Controllers/ProductoInventarioController.php` | Nuevo controlador. | CRUD con baja logica de productos. |
| `app/Http/Controllers/MovimientoInventarioController.php` | Nuevo controlador. | Listado y alta de movimientos. |
| `routes/api.php` | Siete rutas nuevas. | Publicar API protegida de inventario. |
| `tests/Feature/InventarioTest.php` | Nueva suite Feature. | Probar reglas y permisos. |
| `docs/FASE_6_INVENTARIO.md` | Documentacion de fase. | Registrar decisiones, evidencia y pendientes. |

## 8. Pruebas agregadas o modificadas

| Archivo de prueba | Prueba | Que valida | Resultado |
|---|---|---|---|
| `tests/Feature/InventarioTest.php` | Alta por admin con stock inicial | Crea producto activo y movimiento inicial con empleado. | Pasa |
| `tests/Feature/InventarioTest.php` | Alta por recepcionista | El rol operativo puede crear productos. | Pasa |
| `tests/Feature/InventarioTest.php` | Dentista y anonimo | Devuelven `403` y `401`. | Pasa |
| `tests/Feature/InventarioTest.php` | Datos invalidos y nombre duplicado | Rechaza montos negativos, campos derivados y duplicados activos. | Pasa |
| `tests/Feature/InventarioTest.php` | Listado y bajo stock | Solo lista activos y calcula indicador. | Pasa |
| `tests/Feature/InventarioTest.php` | Show/update/destroy | Bloquea stock directo, realiza baja logica y rechaza inactivos. | Pasa |
| `tests/Feature/InventarioTest.php` | Entrada | Aumenta stock y registra autor autenticado. | Pasa |
| `tests/Feature/InventarioTest.php` | Salida | Disminuye stock y evita resultado negativo. | Pasa |
| `tests/Feature/InventarioTest.php` | Ajuste decimal | Establece inventario fisico con dos decimales. | Pasa |
| `tests/Feature/InventarioTest.php` | Campos derivados/cantidades invalidas | Impide manipular autor o stocks calculados. | Pasa |
| `tests/Feature/InventarioTest.php` | Producto inactivo e historial | Bloquea nuevos movimientos y conserva consulta historica. | Pasa |
| `tests/Feature/InventarioTest.php` | Permisos dentista | No puede consultar ni registrar inventario. | Pasa |

## 9. Comandos ejecutados

Las pruebas se ejecutaron sobre `dentista_db_testing` mediante `APP_ENV=testing`, `DB_CONNECTION=mysql` y `DB_DATABASE=dentista_db_testing`. No se utilizo la base principal `dentista_db`.

| Comando | Resultado | Observaciones |
|---|---|---|
| `rg -n -i "inventario|producto|insumo|stock|existencia|movimiento|entrada|salida|almacen" app database routes tests docs` | Correcto | Confirmo ausencia de implementacion de inventario. |
| `php -l` sobre archivos PHP nuevos de inventario | Correcto | Sin errores de sintaxis. |
| `php artisan config:clear` | Correcto | Preparacion de testing. |
| `php artisan cache:clear` | Correcto | Preparacion de testing. |
| `php artisan migrate:fresh --seed --database=mysql --force` (primer intento) | Fallo controlado | MySQL rechazo nombre automatico demasiado largo de indice en la migration nueva de movimientos. |
| Ajuste de migration: indices `mov_inv_producto_fecha_idx` y `mov_inv_tipo_idx` | Correcto | Correccion limitada al nuevo modulo antes de validarlo. |
| `php artisan migrate:fresh --seed --database=mysql --force` (segundo intento) | Correcto | Reconstruyo `dentista_db_testing` con 20 migraciones. |
| `php artisan migrate:status --database=mysql` | Correcto | Incluye tablas de comprobantes y las dos tablas de inventario. |
| `php artisan test --filter=InventarioTest` | Correcto: 12 pruebas, 79 aserciones | Cobertura funcional del modulo. |
| `php artisan route:list --path=api/inventario -v` | Correcto: 7 rutas | Confirma autenticacion, empleado activo y roles permitidos. |
| `php artisan test --filter=AuthTest` | Correcto: 13 pruebas, 29 aserciones | Regresion de seguridad. |
| `php artisan test --filter=CitaTest` | Correcto: 23 pruebas, 52 aserciones | Regresion de agenda. |
| `php artisan test --filter=ComprobanteTest` | Correcto: 16 pruebas, 74 aserciones | Regresion de comprobantes. |
| `php artisan test --filter=PagoTest` | Correcto: 20 pruebas, 62 aserciones | Regresion financiera. |
| `php artisan test --filter=CorteTest` | Correcto: 12 pruebas, 30 aserciones | Regresion financiera. |
| `php artisan test --filter=PersonaTest` | Correcto: 16 pruebas, 47 aserciones | Regresion de pacientes. |
| `php artisan test` | Correcto: 130 pruebas, 406 aserciones | Suite completa verde; duracion aproximada 5.69 s. |
| `git diff --check` | Correcto | Sin errores de espacios en el diff. |

Detalle del reinicio de datos:

| Dato | Valor |
|---|---|
| Comando ejecutado | `php artisan migrate:fresh --seed --database=mysql --force` |
| Base usada | `dentista_db_testing` |
| Motivo | Aplicar y validar migrations nuevas y ejecutar pruebas reproducibles. |
| Resultado | Tras corregir el indice nuevo, 20 migraciones aplicadas y suite completa verde. |
| Datos afectados | Solo datos temporales de testing; no afecto la base principal. |

## 10. Resultado final

- `php artisan test --filter=InventarioTest` paso: 12 pruebas y 79 aserciones.
- Las pruebas especificas de productos y movimientos estan incluidas en `InventarioTest` y pasaron.
- `ComprobanteTest`, `PagoTest`, `CorteTest` y `PersonaTest` pasaron como regresion.
- `AuthTest` y `CitaTest` tambien pasaron para proteger fases previas.
- `php artisan test` paso completo: 130 pruebas y 406 aserciones.
- INV-001 quedo cerrado: ya existe un modulo minimo de inventario.
- INV-002 quedo cerrado: existencias se controlan por movimientos transaccionales, sin edicion directa de stock.

## 11. Riesgos o pendientes

- No existe integracion con compras o proveedores.
- No existe integracion con ventas ni facturacion.
- No existe descuento automatico de insumos al realizar servicios clinicos.
- No se gestionan lotes, numeros de serie, fechas de caducidad ni alertas por vencimiento.
- No se implementaron reversos o cancelaciones auditadas de movimientos; cualquier correccion futura debe registrarse como nuevo ajuste.
- No se implementaron almacenes multiples ni sucursales.
- La unicidad de nombre para productos activos esta protegida en aplicacion; escenarios de altas concurrentes extremas pueden requerir un diseno adicional de restriccion en base de datos.
- No existen reportes historicos avanzados, kardex exportable ni alertas automatizadas de bajo stock.

## 12. Notas para el siguiente desarrollador

- La logica central de stock esta en `app/Services/InventarioService.php`.
- El frontend debe enviar `stockInicial` al crear producto y no puede editar `stockActual` directamente.
- Para modificar stock, el frontend debe registrar `entrada`, `salida` o `ajuste` mediante `/api/inventario/movimientos`.
- En un ajuste, `cantidad` representa el nuevo conteo fisico total, no una diferencia a sumar o restar.
- `stockAnterior`, `stockNuevo`, `idEmpleado` y `fechaMovimiento` siempre pertenecen al backend.
- `InventarioTest` protege las reglas de existencias, permisos, baja logica e historial.
- Fases futuras no deben vincular consumo a citas o servicios sin definir cantidades, reversos y auditoria.
- Compras, ventas, proveedores, caducidad y multi-almacen quedan fuera del alcance de esta base funcional.
