# Reporte general para exposición del backend DentalSys

## 1. Introducción
DentalSys es un sistema web para consultorio dental. El backend es una API REST construida con Laravel y MySQL, pensada para ser consumida por un frontend web desde la URL local `http://localhost:8000/api`.

El backend concentra la lógica de negocio del consultorio: autenticación, empleados, pacientes, agenda de citas, servicios, recetas, caja, pagos, cortes, comprobantes internos, inventario, dashboard e historiales.

Tecnologías verificadas:
- Framework real: Laravel 10.50.0.
- Base de datos: MySQL en entorno local con Laragon.
- Autenticación: Laravel Sanctum 3.3.3 con tokens Bearer.
- Usuario autenticable real: `App\Models\Empleado`.
- Entrada principal de API: `routes/api.php`.

Observación documental:
- `README.md` menciona Laravel 11, pero el comando `php artisan --version` confirmó Laravel 10.50.0. Para exposición conviene decir que el estado real del proyecto es Laravel 10.50.0.

## 2. Objetivo del backend
El backend busca resolver la operación central de un consultorio dental:

- Gestión de empleados y roles.
- Gestión de pacientes/personas.
- Agenda de citas.
- Catálogo de servicios y clases de servicio.
- Recetas asociadas a citas.
- Pagos y cortes de caja.
- Comprobantes internos de pago.
- Inventario de productos/insumos.
- Reglas de consumo de inventario por servicio.
- Consumo automático de inventario por cita.
- Dashboard con métricas operativas.
- Historiales de citas y pagos por paciente.
- Seguridad de acceso mediante tokens, middleware de empleado activo y control por rol.

## 3. Arquitectura general
El proyecto usa Laravel MVC aplicado a una API REST. Aunque Laravel permite vistas, en este backend la parte importante está en rutas API, controladores, validaciones, modelos, recursos JSON, servicios de dominio, migraciones y pruebas.

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| Rutas API | `routes/api.php` | Define endpoints bajo `/api` y asigna middleware/controladores |
| Controllers | `app/Http/Controllers` | Orquestan la petición HTTP y llaman modelos o servicios |
| FormRequests | `app/Http/Requests` | Validan datos de entrada y reglas de request |
| Models | `app/Models` | Representan tablas y relaciones Eloquent |
| Resources | `app/Http/Resources` | Formatean respuestas JSON para frontend |
| Services | `app/Services` | Contienen lógica compleja de negocio |
| Middleware | `app/Http/Middleware` | Controlan autenticación, estado activo y roles |
| Migraciones | `database/migrations` | Definen estructura de base de datos |
| Factories | `database/factories` | Construyen datos para pruebas automatizadas |
| Seeders | `database/seeders` | Generan datos iniciales/controlados |
| Tests Feature | `tests/Feature` | Prueban rutas reales y reglas funcionales |

## 4. Flujo general de una petición API
1. El frontend llama a un endpoint bajo `/api`.
2. Laravel recibe la ruta definida en `routes/api.php`.
3. Si la ruta está protegida, pasa por `auth:sanctum`.
4. Se valida que el empleado siga activo con `empleado.activo`.
5. Si la ruta lo requiere, se valida el rol con `rol:*`.
6. Si el endpoint recibe datos, un FormRequest valida el payload.
7. El controller orquesta la operación.
8. Si hay lógica compleja, el controller delega a un Service.
9. Eloquent consulta o modifica MySQL.
10. El backend responde JSON mediante un Resource o un arreglo estructurado.

Ejemplo conceptual:

```text
Frontend -> /api/pagos -> auth:sanctum -> empleado.activo -> rol:admin,recepcionista
         -> StorePagoRequest -> PagoController -> CajaService -> MySQL -> PagoResource -> JSON
```

## 5. Autenticación y seguridad
El login se hace con:

```http
POST /api/login
```

El backend valida credenciales del empleado. Si son correctas y el empleado está activo, genera un token de Sanctum. El frontend debe enviar ese token en las siguientes peticiones usando el header:

```http
Authorization: Bearer {token}
```

Headers recomendados:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

Reglas de seguridad observadas:
- Las rutas protegidas usan `auth:sanctum`.
- Después de autenticar, las rutas pasan por `empleado.activo`.
- Un empleado inactivo no puede seguir usando la API.
- Si un empleado inactivo intenta usar un token, el middleware elimina el token actual y responde `401`.
- Los tokens se revocan cuando un empleado se da de baja.
- Los tokens se revocan cuando se hace reset administrativo de contraseña.
- Sanctum tiene expiración configurable con `SANCTUM_TOKEN_EXPIRATION`, con default de 480 minutos.
- Existe recuperación pública de contraseña por palabra clave con `POST /api/recover-password-keyword`.
- La recuperación por palabra clave no requiere token.
- La recuperación actualiza contraseña con hash, revoca tokens existentes y usa rate limit `throttle:10,1`.

### Flujo de login
1. Frontend manda `usuario` y contraseña.
2. Backend busca un `Empleado` activo.
3. Backend valida la contraseña con hash.
4. Backend genera un token Bearer de Sanctum.
5. Frontend guarda el token.
6. Frontend manda el token en siguientes peticiones.
7. Logout elimina el token actual.

Endpoints de autenticación:
- `POST /api/login`
- `GET /api/me`
- `POST /api/logout`
- `POST /api/change-password`
- `POST /api/recover-password-keyword`

## 6. Tipos de usuarios y roles
El proyecto maneja tres roles principales mediante `TipoEmpleado` y el middleware `CheckRol`:

- Administrador.
- Recepcionista.
- Dentista.

El middleware normaliza nombres como `administrador`, `admin`, `dentista` y `recepcionista`.

| Rol | Funciones principales | Restricciones |
|---|---|---|
| Administrador | Administración de catálogos, empleados, reset de contraseña, pagos seleccionados, recetas, reglas de consumo, inventario, caja, comprobantes y consulta general | No tiene restricciones funcionales relevantes dentro del alcance probado |
| Recepcionista | Opera pacientes, citas, pagos, cortes, comprobantes, inventario operativo, movimientos de inventario y consumo automático por cita | No gestiona empleados ni reglas de consumo por servicio |
| Dentista | Acceso clínico a recetas y consulta de citas/personas según rutas de lectura | No gestiona pagos, cortes, comprobantes, inventario operativo ni consumo automático por cita |

## 7. Módulos implementados

### 7.1 Autenticación
Propósito:
- Permitir inicio de sesión, consulta de usuario autenticado, logout, cambio de contraseña y recuperación por palabra clave.

Archivos principales:
- `AuthController`.
- `RecoverPasswordKeywordRequest`.
- `EnsureEmpleadoIsActive`.
- `CheckRol`.
- Configuración Sanctum.

Endpoints:
- `POST /api/login`
- `GET /api/me`
- `POST /api/logout`
- `POST /api/change-password`
- `POST /api/recover-password-keyword`

Reglas importantes:
- Login sólo para empleados activos.
- Token Bearer para rutas protegidas.
- Logout elimina token actual.
- Recuperación por palabra clave revoca tokens previos.
- Tokens tienen expiración configurable.

Pruebas relacionadas:
- `AuthTest`.

### 7.2 Empleados / usuarios
Propósito:
- Administrar empleados del consultorio y sus credenciales.

Archivos principales:
- `EmpleadoController`.
- `Empleado`.
- `TipoEmpleado`.
- `EmpleadoResource`.
- `StoreEmpleadoRequest`.
- `UpdateEmpleadoRequest`.
- `ResetPasswordEmpleadoRequest`.

Relaciones:
- `Empleado` pertenece a `Persona`.
- `Empleado` pertenece a `TipoEmpleado`.

Reglas:
- Sólo admin crea, actualiza, elimina o resetea empleados.
- La baja es lógica mediante `estado=false`.
- Al dar de baja se revocan tokens.
- El reset administrativo cambia contraseña, activa bandera de cambio y revoca tokens.

Endpoints relevantes:
- `GET /api/empleados`
- `POST /api/empleados`
- `GET /api/empleados/{empleado}`
- `PUT/PATCH /api/empleados/{empleado}`
- `DELETE /api/empleados/{empleado}`
- `POST /api/empleados/{empleado}/reset-password`

Pruebas relacionadas:
- `EmpleadoTest`.

### 7.3 Pacientes / personas
Propósito:
- Gestionar pacientes/personas del consultorio.

Archivos principales:
- `PersonaController`.
- `HistorialPacienteController`.
- `StorePersonaRequest`.
- `UpdatePersonaRequest`.
- `PersonaResource`.
- `Persona`.

Reglas:
- Las personas creadas quedan activas.
- La baja es lógica mediante `estado=false`.
- Personas inactivas no aparecen en listados normales.
- Personas inactivas devuelven `404` en consulta directa y update.
- La búsqueda respeta estado activo.
- Se valida correo único.
- El modelo `Persona` también se usa como datos personales de empleados; por eso algunas métricas de dashboard excluyen personas asociadas a empleados.

Endpoints:
- `GET /api/personas`
- `POST /api/personas`
- `GET /api/personas/{persona}`
- `PUT/PATCH /api/personas/{persona}`
- `DELETE /api/personas/{persona}`
- `GET /api/personas/{persona}/historial-citas`
- `GET /api/personas/{persona}/historial-pagos`

Pruebas relacionadas:
- `PersonaTest`.
- `HistorialPacienteTest`.

### 7.4 Servicios
Propósito:
- Mantener el catálogo operativo de servicios dentales.

Archivos principales:
- `ServicioController`.
- `Servicio`.
- `ClaseServicio`.
- `ServicioResource`.
- `StoreServicioRequest`.
- `UpdateServicioRequest`.

Relaciones:
- `Servicio` pertenece a `ClaseServicio`.
- `Servicio` tiene muchas citas.
- `Servicio` puede tener reglas de consumo de inventario.

Endpoints principales:
- `GET /api/servicios`
- `POST /api/servicios`
- `GET /api/servicios/{servicio}`
- `PUT/PATCH /api/servicios/{servicio}`
- `DELETE /api/servicios/{servicio}`
- `GET /api/clases-servicio`
- CRUD de clases de servicio para admin.

Uso:
- Se usa en agenda de citas.
- Se usa en reglas de consumo de inventario por servicio.

### 7.5 Citas / agenda
Propósito:
- Programar y consultar citas del consultorio.

Archivos principales:
- `CitaController`.
- `StoreCitaRequest`.
- `UpdateCitaRequest`.
- `CitaResource`.
- `Cita`.
- `DisponibilidadCitaService`.

Reglas:
- Las citas nuevas requieren un dentista activo mediante `idEmpleado`.
- El empleado asignado debe tener rol dentista.
- La validación de dentista normaliza mayúsculas y espacios.
- No se permiten traslapes de agenda por dentista, fecha, hora y duración.
- Al actualizar se excluye la cita actual para evitar colisión consigo misma.
- Citas inactivas no bloquean disponibilidad.
- Citas históricas pueden tener dentista `null`.
- El Resource tolera dentista `null`.

Endpoint de consumo automático:
- `POST /api/citas/{cita}/consumir-inventario`

Reglas de consumo:
- Valida stock suficiente.
- Crea movimientos reales de salida.
- Bloquea doble consumo con `409`.
- No permite consumos parciales.

Pruebas relacionadas:
- `CitaTest`.
- `ConsumoInventarioCitaTest`.

### 7.6 Recetas
Propósito:
- Registrar recetas asociadas a citas.

Archivos principales:
- `RecetaController`.
- `Receta`.
- `RecetaResource`.
- `StoreRecetaRequest`.
- `UpdateRecetaRequest`.

Reglas:
- Admin y dentista pueden crear/consultar/actualizar recetas.
- Sólo admin puede eliminar recetas.
- Recepcionista no accede a recetas según rutas actuales.
- Una cita no debe tener dos recetas activas.
- Baja lógica en eliminación.

Endpoints:
- `GET /api/recetas`
- `POST /api/recetas`
- `GET /api/recetas/{receta}`
- `PUT/PATCH /api/recetas/{receta}`
- `DELETE /api/recetas/{receta}`

Pruebas relacionadas:
- `RecetaTest`.

### 7.7 Pagos
Propósito:
- Registrar pagos del consultorio con reglas financieras consistentes.

Archivos principales:
- `PagoController`.
- `StorePagoRequest`.
- `UpdatePagoRequest`.
- `PagoResource`.
- `Pago`.
- `CajaService`.

Reglas:
- No se manejan pagos parciales en esta fase.
- `total > 0`.
- `efectivo >= 0`.
- `tarjeta >= 0`.
- `efectivo + tarjeta = total`.
- `idEmpleado` lo asigna backend desde el usuario autenticado.
- `idCorte` lo asigna backend desde el corte activo.
- `pagado` se deriva por backend.
- No se puede crear pago sin corte abierto.
- No se editan ni desactivan pagos de cortes cerrados.
- No se puede mover un pago a otro corte.

Endpoints:
- `GET /api/pagos`
- `POST /api/pagos`
- `GET /api/pagos/{pago}`
- `PUT/PATCH /api/pagos/{pago}`
- `DELETE /api/pagos/{pago}`

Pruebas relacionadas:
- `PagoTest`.

### 7.8 Cortes de caja
Propósito:
- Controlar apertura y cierre de caja.

Archivos principales:
- `CorteController`.
- `CorteResource`.
- `Corte`.
- `CajaService`.
- `StoreCorteRequest`.
- `UpdateCorteRequest`.

Reglas:
- Sólo puede existir un corte abierto.
- Un corte abierto no tiene `fechaFin`.
- Al cerrar corte, backend calcula `tEfectivo` y `tTarjeta` desde pagos activos.
- El frontend no controla totales de cierre.
- Un corte cerrado es inmutable.
- No se modifican pagos de corte cerrado.

Endpoints:
- `GET /api/cortes`
- `POST /api/cortes`
- `GET /api/cortes/activo`
- `GET /api/cortes/{corte}`
- `PUT/PATCH /api/cortes/{corte}`
- `DELETE /api/cortes/{corte}`

Pruebas relacionadas:
- `CorteTest`.

### 7.9 Comprobantes internos
Propósito:
- Emitir recibos/comprobantes internos ligados a pagos.

Archivos principales:
- `ComprobanteController`.
- `ComprobanteService`.
- `ComprobanteResource`.
- `Comprobante`.
- `StoreComprobanteRequest`.

Reglas:
- Son comprobantes internos, no CFDI.
- No hay integración SAT/PAC.
- El folio lo genera backend.
- Sólo un comprobante por pago.
- Se guarda snapshot de importes del pago.
- Cancelación lógica mediante `estado=false`.
- Cancelar comprobante no modifica pago ni corte.
- Se puede emitir comprobante de pago perteneciente a corte cerrado porque no altera caja.

Endpoints:
- `GET /api/comprobantes`
- `POST /api/comprobantes`
- `GET /api/comprobantes/{comprobante}`
- `DELETE /api/comprobantes/{comprobante}`

Pruebas relacionadas:
- `ComprobanteTest`.

### 7.10 Inventario
Propósito:
- Administrar productos/insumos y movimientos de stock.

Archivos principales:
- `ProductoInventarioController`.
- `MovimientoInventarioController`.
- `InventarioService`.
- `ProductoInventarioResource`.
- `MovimientoInventarioResource`.
- `ProductoInventario`.
- `MovimientoInventario`.

Reglas:
- Productos tienen `stockActual`, `stockMinimo`, `unidadMedida`, `costoUnitario` opcional y `estado`.
- `stockActual` no se edita directamente desde el frontend.
- Todo cambio de stock debe generar movimiento.
- Movimientos disponibles: `entrada`, `salida`, `ajuste`.
- `stockAnterior` y `stockNuevo` los calcula backend.
- `idEmpleado` lo toma del usuario autenticado.
- No se permite stock negativo.
- Productos inactivos no reciben movimientos.
- Resource puede indicar `bajoStock`.

Endpoints:
- `GET /api/inventario/productos`
- `POST /api/inventario/productos`
- `GET /api/inventario/productos/{producto}`
- `PUT/PATCH /api/inventario/productos/{producto}`
- `DELETE /api/inventario/productos/{producto}`
- `GET /api/inventario/movimientos`
- `POST /api/inventario/movimientos`

Pruebas relacionadas:
- `InventarioTest`.

### 7.11 Consumo de inventario por servicio
Propósito:
- Definir cuánto producto consume un servicio dental.

Archivos principales:
- `ConsumoServicioController`.
- `ConsumoServicioResource`.
- `ConsumoServicio`.
- `StoreConsumoServicioRequest`.
- `UpdateConsumoServicioRequest`.

Reglas:
- Sólo admin configura reglas.
- Una regla relaciona `idServicio` con `idProductoInventario` y `cantidad`.
- Crear reglas no modifica stock.
- No se permiten duplicados activos para la misma combinación servicio-producto.
- DELETE hace baja lógica.
- Reglas inactivas no aparecen en listado normal.

Endpoints:
- `GET /api/inventario/consumos-servicio`
- `POST /api/inventario/consumos-servicio`
- `GET /api/inventario/consumos-servicio/{consumoServicio}`
- `PUT/PATCH /api/inventario/consumos-servicio/{consumoServicio}`
- `DELETE /api/inventario/consumos-servicio/{consumoServicio}`

Pruebas relacionadas:
- `ConsumoServicioTest`.

### 7.12 Consumo automático por cita
Propósito:
- Aplicar salidas de inventario asociadas al servicio de una cita.

Archivos principales:
- `ConsumoInventarioCitaController`.
- `ConsumoInventarioCitaService`.
- `ConsumoInventarioCita`.
- `ConsumirInventarioCitaRequest`.

Reglas:
- Admin y recepcionista pueden ejecutar.
- Dentista no puede ejecutar.
- Payload requiere `confirmar=true`.
- La cita debe existir y estar activa.
- El servicio debe tener reglas activas.
- Se valida stock suficiente antes de descontar.
- Se crean movimientos de salida reales.
- Se bloquea doble consumo con `409`.
- No hay consumos parciales.
- Se registra trazabilidad en `consumos_inventario_cita` y su pivote de movimientos.

Endpoint:
- `POST /api/citas/{cita}/consumir-inventario`

Pruebas relacionadas:
- `ConsumoInventarioCitaTest`.

### 7.13 Dashboard
Propósito:
- Entregar métricas resumidas al frontend.

Archivo principal:
- `DashboardController`.

Endpoint:
- `GET /api/dashboard/resumen`

Métricas:
- `pacientesActivos`.
- `citasHoy`.
- `ingresosHoy`.
- `productosBajoStock`.
- `citasProximas`.
- `alertasInventario`.

Reglas:
- No usa números hardcodeados.
- Usa fecha actual del servidor.
- Devuelve ceros y arreglos vacíos si no hay datos.
- `pacientesActivos` excluye personas asociadas a empleados.

Pruebas relacionadas:
- `DashboardTest`.

## 8. Validaciones principales del sistema
| Módulo | Validaciones principales | Error esperado |
|---|---|---|
| Auth | Usuario requerido, contraseña requerida, empleado activo, palabra clave, contraseña nueva confirmada | `401`, `422` |
| Pacientes | Campos requeridos, correo válido/único, activos en endpoints normales | `404`, `422` |
| Citas | Dentista activo, servicio existente, no traslapes por duración, cita activa | `404`, `422` |
| Pagos | `total > 0`, montos no negativos, efectivo + tarjeta = total, corte activo requerido | `422` |
| Cortes | Sólo un corte abierto, corte cerrado inmutable, cierre calcula totales | `422` |
| Comprobantes | Pago existente, pago activo/liquidado, folio único, un comprobante por pago | `404`, `422` |
| Inventario | Producto activo, stock no negativo, campos derivados prohibidos, movimientos obligatorios | `404`, `422` |
| Consumo por servicio | Servicio existente, producto activo, cantidad > 0, duplicado activo rechazado | `422` |
| Consumo por cita | Reglas activas, stock suficiente, `confirmar=true`, no doble consumo | `404`, `409`, `422` |
| Roles | Middleware `rol:*` valida permisos | `403` |
| Token | Middleware `auth:sanctum` valida token | `401` |

## 9. Controllers: qué hacen
Un controller recibe la petición HTTP, llama validaciones, consulta modelos o delega lógica a services y devuelve respuestas JSON.

| Controller | Responsabilidad |
|---|---|
| `AuthController` | Login, logout, usuario actual, cambio y recuperación de contraseña |
| `PersonaController` | CRUD de pacientes/personas y baja lógica |
| `HistorialPacienteController` | Historial de citas y pagos por persona |
| `EmpleadoController` | CRUD de empleados, reset administrativo y baja lógica |
| `CitaController` | CRUD de citas y agenda |
| `PagoController` | Registro, consulta, actualización y baja lógica de pagos |
| `CorteController` | Apertura, consulta, cierre/actualización y baja de cortes |
| `ComprobanteController` | Emisión, consulta y cancelación de comprobantes internos |
| `ProductoInventarioController` | CRUD de productos/insumos |
| `MovimientoInventarioController` | Registro y consulta de movimientos |
| `ConsumoServicioController` | CRUD de reglas de consumo por servicio |
| `ConsumoInventarioCitaController` | Aplicación de consumo automático por cita |
| `DashboardController` | Métricas resumidas para frontend |
| `RecetaController` | CRUD funcional de recetas |
| `ServicioController` | CRUD de servicios |
| `TipoEmpleadoController` | Catálogo de tipos de empleado |
| `ClaseServicioController` | Catálogo de clases de servicio |

## 10. FormRequests: qué validan
Los FormRequests centralizan validación de entrada antes de ejecutar la lógica del controller.

| Request | Qué valida |
|---|---|
| `RecoverPasswordKeywordRequest` | Usuario, palabra clave, contraseña nueva y confirmación |
| `ResetPasswordEmpleadoRequest` | Payload de reset administrativo de contraseña |
| `StoreEmpleadoRequest` | Datos de empleado, persona relacionada, credenciales y rol |
| `UpdateEmpleadoRequest` | Actualización permitida de empleado |
| `StorePersonaRequest` | Datos requeridos de persona y correo |
| `UpdatePersonaRequest` | Actualización de persona y unicidad de correo |
| `StoreCitaRequest` | Persona, servicio, dentista, fecha/hora y disponibilidad |
| `UpdateCitaRequest` | Cambios parciales de cita y disponibilidad |
| `StorePagoRequest` | Montos consistentes y campos financieros permitidos |
| `UpdatePagoRequest` | Montos consistentes y bloqueo de campos derivados |
| `StoreCorteRequest` | Apertura de corte sin totales manuales indebidos |
| `UpdateCorteRequest` | Cierre/actualización de corte y bloqueo de totales manuales |
| `StoreComprobanteRequest` | Pago existente y campos permitidos de comprobante |
| `StoreProductoInventarioRequest` | Producto, stock inicial, unidad y campos derivados prohibidos |
| `UpdateProductoInventarioRequest` | Datos generales sin editar stock directo |
| `StoreMovimientoInventarioRequest` | Tipo de movimiento, cantidad y campos derivados prohibidos |
| `StoreConsumoServicioRequest` | Regla de consumo, cantidad y duplicados activos |
| `UpdateConsumoServicioRequest` | Actualización de regla y duplicados activos |
| `ConsumirInventarioCitaRequest` | Confirmación explícita del consumo |
| `StoreRecetaRequest` | Datos de receta y cita relacionada |
| `UpdateRecetaRequest` | Actualización de receta |
| `StoreServicioRequest` | Datos de servicio |
| `UpdateServicioRequest` | Actualización de servicio |
| `StoreTipoEmpleadoRequest` | Datos de tipo de empleado |
| `UpdateTipoEmpleadoRequest` | Actualización de tipo de empleado |
| `StoreClaseServicioRequest` | Datos de clase de servicio |
| `UpdateClaseServicioRequest` | Actualización de clase de servicio |

## 11. Services: lógica de negocio compleja
Los Services encapsulan reglas que serían demasiado pesadas para un controller. Esto ayuda a mantener controladores simples y lógica reusable/probable.

| Service | Responsabilidad |
|---|---|
| `CajaService` | Registrar pagos, abrir/cerrar cortes, validar corte abierto, calcular totales y proteger cortes cerrados |
| `DisponibilidadCitaService` | Validar dentista activo y detectar traslapes de agenda por duración |
| `ComprobanteService` | Emitir comprobantes, generar folio, validar pago liquidado y cancelar comprobantes |
| `InventarioService` | Crear productos, registrar movimientos, calcular stock y evitar stock negativo |
| `ConsumoInventarioCitaService` | Validar reglas/stock y aplicar consumo automático transaccional por cita |

## 12. Resources: respuestas JSON
Los Resources definen cómo se devuelven los datos al frontend. Ayudan a mantener contratos estables y evitar exponer estructura interna de modelos.

Resources principales:
- `PersonaResource`: datos de persona/paciente.
- `CitaResource`: cita, servicio, paciente y dentista.
- `PagoResource`: pago, persona, empleado y corte.
- `CorteResource`: corte y totales.
- `ComprobanteResource`: folio, snapshot de importes, paciente y cajero.
- `ProductoInventarioResource`: producto, stock, costo y bajo stock.
- `MovimientoInventarioResource`: movimiento, producto y empleado.
- `ConsumoServicioResource`: regla de consumo por servicio.
- `EmpleadoResource`: empleado, persona y tipo.
- `ServicioResource`: servicio y clase.
- `RecetaResource`: receta.
- `TipoEmpleadoResource`: catálogo de roles.
- `ClaseServicioResource`: catálogo de clases.

## 13. Base de datos y relaciones principales
| Entidad | Relaciones principales |
|---|---|
| `Persona` | Tiene muchas citas, tiene muchos pagos, puede tener un empleado asociado |
| `Empleado` | Pertenece a persona y tipoEmpleado; es el usuario autenticable |
| `TipoEmpleado` | Tiene muchos empleados |
| `ClaseServicio` | Tiene muchos servicios |
| `Servicio` | Pertenece a claseServicio, tiene muchas citas, tiene reglas de consumo |
| `Cita` | Pertenece a persona, servicio y dentista/empleado; tiene una receta; tiene consumo de inventario |
| `Receta` | Pertenece a cita |
| `Pago` | Pertenece a persona, empleado y corte; puede tener comprobante |
| `Corte` | Tiene muchos pagos |
| `Comprobante` | Pertenece a pago |
| `ProductoInventario` | Tiene muchos movimientos y reglas de consumo |
| `MovimientoInventario` | Pertenece a producto y empleado |
| `ConsumoServicio` | Relaciona servicio con productoInventario |
| `ConsumoInventarioCita` | Relaciona cita, empleado y movimientos de inventario |

## 14. API: dónde se encuentran y cómo se consumen
Las rutas están en:

```text
routes/api.php
```

Todas usan el prefijo:

```text
/api
```

Base URL local esperada:

```text
http://localhost:8000/api
```

Se verificaron con:

```bash
php artisan route:list --path=api -v
```

El route-list mostró 72 rutas API.

| Módulo | Endpoint principal | Auth | Roles |
|---|---|---|---|
| Auth | `POST /api/login` | No | Público |
| Recuperación | `POST /api/recover-password-keyword` | No | Público con throttle |
| Sesión | `GET /api/me`, `POST /api/logout` | Sí | Todos autenticados |
| Personas | `/api/personas` | Sí | Lectura todos; escritura admin/recepción |
| Historiales | `/api/personas/{id}/historial-citas` | Sí | Todos autenticados |
| Historial pagos | `/api/personas/{id}/historial-pagos` | Sí | Admin/recepción |
| Empleados | `/api/empleados` | Sí | Lectura todos; escritura admin |
| Servicios | `/api/servicios` | Sí | Lectura todos; escritura admin/recepción |
| Citas | `/api/citas` | Sí | Lectura todos; escritura admin/recepción |
| Recetas | `/api/recetas` | Sí | Admin/dentista; delete sólo admin |
| Pagos | `/api/pagos` | Sí | Admin/recepción; update admin |
| Cortes | `/api/cortes` | Sí | Admin/recepción |
| Comprobantes | `/api/comprobantes` | Sí | Admin/recepción |
| Inventario productos | `/api/inventario/productos` | Sí | Admin/recepción |
| Inventario movimientos | `/api/inventario/movimientos` | Sí | Admin/recepción |
| Reglas consumo | `/api/inventario/consumos-servicio` | Sí | Admin |
| Consumo por cita | `POST /api/citas/{id}/consumir-inventario` | Sí | Admin/recepción |
| Dashboard | `GET /api/dashboard/resumen` | Sí | Todos autenticados |

## 15. Códigos HTTP usados
| Código | Significado |
|---|---|
| 200 | Consulta o actualización correcta |
| 201 | Creación correcta |
| 204 | Baja lógica/cancelación sin contenido |
| 401 | No autenticado, token inválido o cuenta inactiva |
| 403 | Rol sin permiso |
| 404 | Recurso inexistente o inactivo |
| 409 | Conflicto, por ejemplo doble consumo de inventario |
| 422 | Error de validación o regla de negocio |
| 500 | Error inesperado |

## 16. Pruebas automatizadas
El proyecto usa tests Feature para validar rutas reales y reglas de negocio. Las pruebas usan `RefreshDatabase`, por lo que reconstruyen datos durante ejecución.

Comando principal:

```bash
php artisan test
```

Resultado final verificado:
- 155 pruebas pasaron.
- 556 aserciones pasaron.
- Duración aproximada del último run: 18.83 segundos.

Suites principales:
- `AuthTest`.
- `EmpleadoTest`.
- `PersonaTest`.
- `CitaTest`.
- `PagoTest`.
- `CorteTest`.
- `ComprobanteTest`.
- `InventarioTest`.
- `HistorialPacienteTest`.
- `DashboardTest`.
- `ConsumoServicioTest`.
- `ConsumoInventarioCitaTest`.
- `RecetaTest`.

Comandos ejecutados en esta revisión:

| Comando | Resultado | Observaciones |
|---|---|---|
| `php artisan --version` | Laravel Framework 10.50.0 | Confirma versión real |
| `php artisan route:list --path=api -v` | 72 rutas API | Confirma endpoints y middleware |
| `php artisan test` | 155 passed, 556 assertions | Suite completa verde |
| Lectura de `REQUERIMIENTOS_BACKEND_PARA_FRONTEND.md` | No existe | No confirmado por archivo, se usaron docs de fases y código real |
| Lectura de `README.md` | Existe | Contiene discrepancia: menciona Laravel 11 |

## 17. Funcionalidades fuera de alcance
No se implementó:
- CFDI/SAT/PAC.
- PDF de comprobantes.
- Envío de correos.
- Compras/proveedores.
- Lotes/caducidad.
- Multi-almacén.
- Reversos automáticos de inventario.
- Reversos automáticos cuando se cancela una cita ya consumida.
- Reportes avanzados.
- Producción/deploy.
- Backups y monitoreo productivo.
- Hardening completo para producción.
- Auditoría avanzada de todas las operaciones.

## 18. Veredicto final del backend
Estado general:
- Listo para demo técnica.
- Listo para integración con frontend dentro del alcance implementado.
- Listo para datos reales de prueba/controlados.
- No se debe declarar listo para producción real todavía.

Justificación:
- La suite completa está verde con 155 pruebas y 556 aserciones.
- Las rutas principales están protegidas con Sanctum, empleado activo y roles.
- Existen reglas de negocio importantes cubiertas por tests: tokens, citas sin traslape, pagos consistentes, cortes inmutables, inventario sin stock negativo, comprobantes internos y consumo por cita.
- Falta trabajo operativo de producción: despliegue, backups, monitoreo, hardening, logs/auditoría avanzada y políticas de recuperación/seguridad más robustas.

## 19. Guion sugerido para exposición
1. Presentación del proyecto:
   - “DentalSys es un sistema web para consultorio dental. El backend es una API REST en Laravel que centraliza datos y reglas de negocio.”

2. Arquitectura:
   - “Usamos Laravel MVC orientado a API: rutas, controladores, modelos, requests, resources y services.”

3. Autenticación:
   - “El usuario real no es `User`, es `Empleado`. El login devuelve un token Bearer de Sanctum.”

4. Seguridad:
   - “Las rutas protegidas pasan por `auth:sanctum`, luego `empleado.activo`, y después por `rol:*` cuando aplica.”

5. Roles:
   - “Admin administra; recepcionista opera agenda, pacientes, caja e inventario; dentista trabaja más en recetas y flujo clínico.”

6. Módulos principales:
   - “Tenemos pacientes, empleados, servicios, citas, recetas, pagos, cortes, comprobantes, inventario, dashboard e historiales.”

7. Validaciones importantes:
   - “Las validaciones no están sólo en frontend. El backend impide pagos incompletos, traslapes de citas, stock negativo y doble consumo de inventario.”

8. Ejemplo de negocio: pagos/cortes:
   - “Un pago válido debe estar liquidado. El corte calcula totales desde pagos reales y un corte cerrado no se modifica.”

9. Ejemplo de negocio: inventario:
   - “El stock no se edita directo; todo cambio genera movimiento. Además, una cita puede consumir inventario automáticamente según reglas del servicio.”

10. Pruebas:
   - “La suite completa pasa con 155 pruebas y 556 aserciones. Son pruebas Feature que validan rutas reales.”

11. Pendientes:
   - “No implementamos CFDI real, PDF, compras, lotes, producción ni monitoreo. Eso queda fuera del alcance actual.”

12. Cierre:
   - “El backend está listo para demo e integración frontend, pero producción requiere una fase operativa adicional.”

## 20. Preguntas posibles y respuestas sugeridas
**¿Por qué usan tokens?**  
Porque es una API REST consumida por frontend. El token Bearer permite autenticar cada petición sin depender de sesión tradicional.

**¿Por qué usan middleware?**  
Porque la autenticación, validación de empleado activo y permisos por rol son reglas transversales. Middleware evita repetir esa lógica en cada controller.

**¿Qué pasa si un empleado se desactiva?**  
No puede seguir usando la API. Si intenta usar un token previo, `empleado.activo` lo rechaza con `401` y elimina el token actual.

**¿Cómo evitan citas duplicadas o traslapadas?**  
Con `DisponibilidadCitaService`, que valida dentista, fecha, hora y duración del servicio. En update excluye la cita actual.

**¿Cómo evitan pagos inconsistentes?**  
Con validaciones y `CajaService`: total debe ser mayor a cero y efectivo + tarjeta debe ser exactamente igual al total. No hay pagos parciales.

**¿Cómo evitan stock negativo?**  
El stock sólo cambia mediante `InventarioService`. En salidas valida que el resultado no sea negativo y calcula `stockAnterior` y `stockNuevo`.

**¿Qué es un Resource?**  
Es una clase que transforma modelos en JSON estable para el frontend, evitando exponer detalles internos de la base de datos.

**¿Qué es un FormRequest?**  
Es una clase de Laravel que valida el payload antes de que el controller ejecute la operación.

**¿Por qué no implementaron factura fiscal?**  
Porque el alcance actual sólo contempla comprobantes internos. CFDI/SAT/PAC requiere integración fiscal real, certificados, proveedor autorizado y reglas legales.

**¿Qué faltaría para producción?**  
Despliegue real, variables seguras, backups, monitoreo, logs, hardening, políticas de rate limit más estrictas, auditoría y pruebas de carga.

**¿Por qué `Persona` también se usa para empleados?**  
Porque el sistema separa datos personales (`Persona`) de datos laborales/de acceso (`Empleado`). Una persona puede estar relacionada con empleado.

**¿Qué pasa si una cita consume inventario dos veces?**  
El segundo intento devuelve `409` porque existe trazabilidad de consumo por cita.

**¿Quién puede configurar consumos por servicio?**  
Sólo admin. Recepcionista puede ejecutar consumo por cita, pero no configurar reglas.

## 21. Resumen corto para memorizar
1. DentalSys es una API REST Laravel para consultorio dental.
2. Usa MySQL en Laragon y Laravel Sanctum con tokens Bearer.
3. El usuario autenticable real es `Empleado`, no el `User` base.
4. Las rutas están en `routes/api.php` y usan prefijo `/api`.
5. Login devuelve token; el frontend manda `Authorization: Bearer {token}`.
6. Las rutas protegidas usan `auth:sanctum`, `empleado.activo` y `rol:*`.
7. Roles principales: admin, recepcionista y dentista.
8. Controllers orquestan, FormRequests validan, Models representan datos, Resources formatean JSON y Services contienen reglas complejas.
9. Citas validan dentista activo y evitan traslapes por duración.
10. Pagos no permiten parciales: efectivo + tarjeta debe ser igual al total.
11. Cortes calculan totales desde pagos y un corte cerrado es inmutable.
12. Comprobantes son internos, no CFDI fiscal.
13. Inventario no permite stock negativo y todo cambio genera movimiento.
14. Consumo por cita descuenta inventario según reglas del servicio y bloquea doble consumo.
15. La suite completa pasó: 155 pruebas y 556 aserciones.

