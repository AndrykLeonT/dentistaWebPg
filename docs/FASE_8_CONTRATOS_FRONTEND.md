# Fase 8 - Contratos backend para frontend

## 1. Objetivo
Implementar los endpoints P1 requeridos por el frontend para que las pantallas preparadas de historiales, dashboard e inventario consuman datos reales del backend.

Esta fase integra:
- Historial de citas del paciente.
- Historial de pagos del paciente.
- Dashboard resumen.
- Configuración de consumo de inventario por servicio.
- Consumo automático de inventario por cita.
- Política final de permisos de inventario para recepcionista.

## 2. Estado inicial
- `GET /api/personas/{id}/historial-citas` no existía.
- `GET /api/personas/{id}/historial-pagos` no existía.
- `GET /api/dashboard/resumen` no existía.
- `/api/inventario/consumos-servicio` no existía.
- `POST /api/citas/{id}/consumir-inventario` no existía.
- El frontend ya tenía pantallas preparadas y marcaba estos puntos como pendientes backend.
- Se reutilizaron módulos existentes de personas, citas, pagos, comprobantes, inventario y Sanctum.
- Se reutilizó `InventarioService` para crear movimientos reales y respetar stock no negativo.

## 3. Contratos implementados

### Historial de citas
| Campo | Valor |
|---|---|
| Método | `GET` |
| Ruta | `/api/personas/{id}/historial-citas` |
| Auth | Sí, `auth:sanctum` + `empleado.activo` |
| Roles | Admin, recepcionista y dentista |
| Payload | No aplica |
| Respuesta | Arreglo con `id`, `fecha`, `hora`, `estado`, `servicio`, `dentista`, `observaciones` |
| Errores | `401` sin token, `404` persona inexistente o inactiva |

Notas:
- `hora` se devuelve en formato `HH:mm`.
- `observaciones` usa el campo real `motivo`.
- Si una cita histórica no tiene dentista, `dentista` devuelve `null`.
- El orden elegido es fecha y hora descendente.

### Historial de pagos
| Campo | Valor |
|---|---|
| Método | `GET` |
| Ruta | `/api/personas/{id}/historial-pagos` |
| Auth | Sí, `auth:sanctum` + `empleado.activo` |
| Roles | Admin y recepcionista |
| Payload | No aplica |
| Respuesta | Arreglo con `id`, `fecha`, `total`, `efectivo`, `tarjeta`, `folioComprobante`, `estado` |
| Errores | `401` sin token, `403` dentista, `404` persona inexistente o inactiva |

Notas:
- `folioComprobante` viene de comprobante activo si existe; si no, devuelve `null`.
- La consulta no modifica pagos ni comprobantes.
- El orden elegido es fecha descendente y luego `idPago` descendente.

### Dashboard resumen
| Campo | Valor |
|---|---|
| Método | `GET` |
| Ruta | `/api/dashboard/resumen` |
| Auth | Sí, `auth:sanctum` + `empleado.activo` |
| Roles | Todos los roles autenticados |
| Payload | No aplica |
| Respuesta | Objeto con métricas reales y arreglos de apoyo |
| Errores | `401` sin token |

Respuesta:

```json
{
  "pacientesActivos": 20,
  "citasHoy": 8,
  "ingresosHoy": 2500,
  "productosBajoStock": 4,
  "citasProximas": [],
  "alertasInventario": []
}
```

Notas:
- `pacientesActivos` cuenta personas activas sin relación con `Empleado`, porque el modelo `Persona` también representa datos personales de empleados.
- `citasHoy` cuenta citas activas de la fecha del servidor.
- `ingresosHoy` suma pagos activos de la fecha del servidor.
- `productosBajoStock` cuenta productos activos con `stockActual <= stockMinimo`.
- No hay números hardcodeados.

### Consumos por servicio
| Campo | Valor |
|---|---|
| Métodos | `GET`, `POST`, `GET {id}`, `PUT/PATCH {id}`, `DELETE {id}` |
| Ruta | `/api/inventario/consumos-servicio` |
| Auth | Sí, `auth:sanctum` + `empleado.activo` |
| Roles | Admin |
| Payload POST/PATCH | `idServicio`, `idProductoInventario`, `cantidad` |
| Respuesta | Resource con servicio, producto, cantidad y activo |
| Errores | `401`, `403`, `404`, `422` |

Respuesta ejemplo:

```json
{
  "data": {
    "id": 1,
    "idServicio": 1,
    "servicio": "Limpieza dental",
    "idProductoInventario": 1,
    "producto": "Guantes",
    "cantidad": "2.00",
    "activo": true
  }
}
```

Reglas:
- Sólo admin gestiona reglas.
- Crear reglas no modifica stock.
- No se permite duplicar una combinación activa `idServicio + idProductoInventario`.
- `DELETE` hace baja lógica con `estado=false`.
- Las reglas inactivas no aparecen en listado normal y devuelven `404` en show/update/delete.

### Consumo automático por cita
| Campo | Valor |
|---|---|
| Método | `POST` |
| Ruta | `/api/citas/{id}/consumir-inventario` |
| Auth | Sí, `auth:sanctum` + `empleado.activo` |
| Roles | Admin y recepcionista |
| Payload | `{ "confirmar": true }` |
| Respuesta | `message` y `movimientos` |
| Errores | `401`, `403`, `404`, `409`, `422` |

Respuesta:

```json
{
  "message": "Consumo de inventario aplicado correctamente.",
  "movimientos": []
}
```

Reglas:
- La cita debe existir y estar activa.
- La cita debe tener servicio con reglas activas.
- Se valida stock suficiente de todos los productos antes de aplicar salidas.
- No hay consumos parciales.
- Se crean movimientos reales de salida usando `InventarioService`.
- Se bloquea el doble consumo con `409`.
- `confirmar` debe ser `true`.

### Política de inventario para recepcionista
- Productos y movimientos base de inventario siguen permitidos para admin y recepcionista, como quedó en Fase 6.
- La configuración de consumos por servicio queda restringida sólo a admin.
- El consumo automático por cita queda permitido para admin y recepcionista.
- Dentista no gestiona inventario ni consume inventario por cita en esta fase.

## 4. Decisiones técnicas
- Se creó `HistorialPacienteController` para evitar cargar `PersonaController` con contratos específicos de frontend.
- Se creó `DashboardController` para concentrar métricas de lectura.
- Se creó `ConsumoServicioController` con `ConsumoServicioResource`.
- Se creó `ConsumoInventarioCitaController` para el endpoint de integración cita-inventario.
- Se creó `ConsumoInventarioCitaService` para la lógica transaccional de consumo.
- Se crearon migraciones nuevas:
  - `consumos_servicio`.
  - `consumos_inventario_cita`.
  - `consumo_inventario_cita_movimientos`.
- `consumos_inventario_cita.idCita` es único para impedir doble consumo activo de una cita.
- Se eligió tabla pivote entre consumo de cita y movimientos para no alterar el contrato base de `movimientos_inventario`.
- La validación de stock se hace antes de aplicar salidas.
- La aplicación de consumo corre dentro de `DB::transaction()`.
- `InventarioService::registrarMovimiento()` se reutiliza para respetar cálculo de `stockAnterior`, `stockNuevo`, empleado autenticado y stock no negativo.
- Dashboard excluye personas asociadas a empleados en `pacientesActivos`.
- Se preservaron rutas y contratos existentes; sólo se agregaron endpoints nuevos y relaciones simples.

## 5. Reglas de negocio aplicadas
- Historiales sólo permiten persona activa.
- Dashboard devuelve ceros y arreglos vacíos si no hay datos.
- Configurar consumo por servicio no modifica stock.
- Consumo por cita crea salidas reales de inventario.
- No hay consumo parcial si falta stock.
- Doble consumo queda bloqueado con `409`.
- Productos inactivos en reglas bloquean el consumo con `422`.
- `confirmar=false` o ausente devuelve `422`.
- Admin configura reglas de consumo.
- Admin y recepcionista ejecutan consumo por cita.
- Dentista queda fuera de inventario operativo.

## 6. Convenciones aplicadas
- Nombres de dominio en español: `ConsumoServicio`, `ConsumoInventarioCita`, `cantidad`, `estado`.
- FormRequest para validación: `StoreConsumoServicioRequest`, `UpdateConsumoServicioRequest`, `ConsumirInventarioCitaRequest`.
- Resources para formato de salida: `ConsumoServicioResource` y `MovimientoInventarioResource`.
- Controllers pequeños para orquestación HTTP.
- Service para lógica transaccional de consumo.
- Relaciones simples en modelos Eloquent.
- Sin dependencias nuevas.
- Sin cambios en contratos existentes de pagos, cortes, comprobantes, citas, personas o inventario base.

## 7. Archivos modificados
| Archivo | Cambio | Motivo |
|---|---|---|
| `routes/api.php` | Nuevas rutas de historiales, dashboard, consumos y consumo por cita | Exponer contratos requeridos por frontend |
| `app/Http/Controllers/HistorialPacienteController.php` | Nuevo controller | Historiales de citas y pagos por paciente |
| `app/Http/Controllers/DashboardController.php` | Nuevo controller | Métricas reales para dashboard |
| `app/Http/Controllers/ConsumoServicioController.php` | Nuevo controller | CRUD de reglas de consumo por servicio |
| `app/Http/Controllers/ConsumoInventarioCitaController.php` | Nuevo controller | Endpoint de consumo automático por cita |
| `app/Services/ConsumoInventarioCitaService.php` | Nuevo service | Validar y aplicar consumo transaccional sin parciales |
| `app/Models/ConsumoServicio.php` | Nuevo modelo | Representar reglas de consumo por servicio |
| `app/Models/ConsumoInventarioCita.php` | Nuevo modelo | Trazabilidad de consumo por cita |
| `app/Models/Servicio.php` | Relación `consumosServicio()` | Navegación Eloquent |
| `app/Models/ProductoInventario.php` | Relación `consumosServicio()` | Navegación Eloquent |
| `app/Models/Cita.php` | Relación `consumoInventario()` | Trazabilidad de consumo por cita |
| `app/Models/Pago.php` | Relación `comprobante()` | Historial de pagos con folio |
| `app/Http/Requests/StoreConsumoServicioRequest.php` | Nuevo FormRequest | Validar creación de reglas |
| `app/Http/Requests/UpdateConsumoServicioRequest.php` | Nuevo FormRequest | Validar actualización de reglas |
| `app/Http/Requests/ConsumirInventarioCitaRequest.php` | Nuevo FormRequest | Validar confirmación explícita |
| `app/Http/Resources/ConsumoServicioResource.php` | Nuevo resource | Contrato JSON de reglas |
| `database/migrations/2026_05_27_000004_create_consumos_servicio_table.php` | Nueva tabla | Configuración de consumos por servicio |
| `database/migrations/2026_05_27_000005_create_consumos_inventario_cita_table.php` | Nueva tabla | Evitar doble consumo y guardar trazabilidad |
| `database/migrations/2026_05_27_000006_create_consumo_inventario_cita_movimientos_table.php` | Nueva tabla pivote | Relacionar consumo de cita con movimientos creados |
| `database/factories/ConsumoServicioFactory.php` | Nueva factory | Datos de prueba para reglas |
| `tests/Feature/HistorialPacienteTest.php` | Nuevas pruebas | Contratos de historiales |
| `tests/Feature/DashboardTest.php` | Nuevas pruebas | Métricas de dashboard |
| `tests/Feature/ConsumoServicioTest.php` | Nuevas pruebas | CRUD y permisos de reglas |
| `tests/Feature/ConsumoInventarioCitaTest.php` | Nuevas pruebas | Consumo automático transaccional |
| `docs/FASE_8_CONTRATOS_FRONTEND.md` | Actualización | Reporte final de fase |

## 8. Pruebas agregadas o modificadas
| Archivo de prueba | Prueba | Qué valida | Resultado |
|---|---|---|---|
| `HistorialPacienteTest` | Historial de citas por roles | Admin, recepcionista y dentista consultan contrato de citas | Pasó |
| `HistorialPacienteTest` | Persona inexistente/inactiva y sin token | `401` y `404` correctos | Pasó |
| `HistorialPacienteTest` | Cita histórica sin dentista | `dentista=null` sin romper | Pasó |
| `HistorialPacienteTest` | Historial de pagos | Admin/recepción, folio opcional y dentista `403` | Pasó |
| `DashboardTest` | Dashboard autenticado | Estructura requerida y `401` sin token | Pasó |
| `DashboardTest` | Dashboard sin datos | Ceros y arreglos vacíos | Pasó |
| `DashboardTest` | Dashboard con datos | Conteos y sumas reales | Pasó |
| `ConsumoServicioTest` | Admin crea regla | Crea regla y no modifica stock | Pasó |
| `ConsumoServicioTest` | Roles | Recepcionista/dentista `403`, sin token `401` | Pasó |
| `ConsumoServicioTest` | Validaciones | Servicio/producto/cantidad inválidos y duplicado activo | Pasó |
| `ConsumoServicioTest` | Baja lógica | Listado activo y `404` para inactivos | Pasó |
| `ConsumoInventarioCitaTest` | Consumo con stock | Crea salidas, descuenta stock y devuelve movimientos | Pasó |
| `ConsumoInventarioCitaTest` | Permisos | Admin/recepción permitidos, dentista `403`, sin token `401` | Pasó |
| `ConsumoInventarioCitaTest` | Errores de negocio | Sin reglas, cita inactiva, confirmar falso, stock insuficiente | Pasó |
| `ConsumoInventarioCitaTest` | Doble consumo | Segundo intento devuelve `409` | Pasó |
| `ConsumoInventarioCitaTest` | Producto inactivo | Devuelve `422` sin descontar stock | Pasó |

## 9. Comandos ejecutados
| Comando | Resultado | Observaciones |
|---|---|---|
| `php artisan config:clear` | Correcto | Ejecutado con `APP_ENV=testing` |
| `php artisan cache:clear` | Correcto | Ejecutado con `APP_ENV=testing` |
| `php artisan migrate:fresh --seed --database=mysql --force` | Correcto | Base `dentista_db_testing`; reinició datos de testing |
| `php artisan route:list --path=api/personas -v` | Correcto | Confirmó historiales y middleware |
| `php artisan route:list --path=api/dashboard -v` | Correcto | Confirmó dashboard autenticado |
| `php artisan route:list --path=api/inventario -v` | Correcto | Confirmó reglas admin-only e inventario base admin/recepción |
| `php artisan route:list --path=api/citas -v` | Correcto | Confirmó consumo por cita admin/recepción |
| `php artisan test --filter=HistorialPacienteTest` | 5 passed, 40 assertions | Pruebas nuevas de historiales |
| `php artisan test --filter=DashboardTest` | 3 passed, 22 assertions | Pruebas nuevas de dashboard |
| `php artisan test --filter=ConsumoServicioTest` | 5 passed, 33 assertions | Pruebas nuevas de reglas |
| `php artisan test --filter=ConsumoInventarioCitaTest` | 6 passed, 29 assertions | Pruebas nuevas de consumo por cita |
| `php artisan test --filter=AuthTest` | 19 passed, 55 assertions | Regresión Fase 1/Fase 7 |
| `php artisan test --filter=PersonaTest` | 16 passed, 47 assertions | Regresión pacientes |
| `php artisan test --filter=CitaTest` | 29 passed, 81 assertions | Incluye `CitaTest` y coincidencia de filtro con `ConsumoInventarioCitaTest` |
| `php artisan test --filter=InventarioTest` | 12 passed, 79 assertions | Regresión inventario base |
| `php artisan test --filter=PagoTest` | 20 passed, 62 assertions | Regresión pagos |
| `php artisan test --filter=CorteTest` | 12 passed, 30 assertions | Regresión cortes |
| `php artisan test --filter=ComprobanteTest` | 16 passed, 74 assertions | Regresión comprobantes |
| `php artisan test` | 155 passed, 556 assertions | Suite completa verde |

Base usada:
- `dentista_db_testing`.
- Se ejecutó `migrate:fresh --seed` sobre testing.
- No se usó la base principal para pruebas destructivas.

Nota:
- Un primer intento de correr suites nuevas en paralelo falló porque `RefreshDatabase` reconstruye la misma base MySQL y las suites se pisaron entre sí. Se repitió de forma secuencial y las pruebas pasaron.

## 10. Resultado final
- Pruebas nuevas pasaron.
- `AuthTest` pasó.
- `PersonaTest` pasó.
- `CitaTest` pasó.
- `InventarioTest` pasó.
- `PagoTest`, `CorteTest` y `ComprobanteTest` pasaron.
- `php artisan test` pasó completo: 155 pruebas, 556 aserciones.

Endpoints que dejaron de responder 404:
- `GET /api/personas/{id}/historial-citas`.
- `GET /api/personas/{id}/historial-pagos`.
- `GET /api/dashboard/resumen`.
- `GET /api/inventario/consumos-servicio`.
- `POST /api/inventario/consumos-servicio`.
- `GET /api/inventario/consumos-servicio/{id}`.
- `PUT/PATCH /api/inventario/consumos-servicio/{id}`.
- `DELETE /api/inventario/consumos-servicio/{id}`.
- `POST /api/citas/{id}/consumir-inventario`.

## 11. Riesgos o pendientes
- Concurrencia extrema: el consumo usa transacción y bloqueo de productos, pero una prueba de carga real quedaría para una fase posterior.
- Reversos de consumo: no se implementó anulación/reverso de salidas por cita.
- Cancelación de cita después de consumir inventario: queda pendiente definir si debe revertir inventario o conservar auditoría.
- No se implementan compras, proveedores, lotes, caducidad ni multi-almacén.
- No se implementan reportes avanzados de consumo.
- Si el frontend decide que dentista debe consultar inventario en modo lectura, se debe abrir una fase específica de permisos.

## 12. Notas para frontend

### URLs finales
- `GET /api/personas/{id}/historial-citas`
- `GET /api/personas/{id}/historial-pagos`
- `GET /api/dashboard/resumen`
- `GET /api/inventario/consumos-servicio`
- `POST /api/inventario/consumos-servicio`
- `GET /api/inventario/consumos-servicio/{id}`
- `PUT /api/inventario/consumos-servicio/{id}`
- `PATCH /api/inventario/consumos-servicio/{id}`
- `DELETE /api/inventario/consumos-servicio/{id}`
- `POST /api/citas/{id}/consumir-inventario`

### Payloads
Crear regla:

```json
{
  "idServicio": 1,
  "idProductoInventario": 1,
  "cantidad": 2
}
```

Consumir inventario por cita:

```json
{
  "confirmar": true
}
```

### Manejo de errores
- `401`: no hay token o token inválido.
- `403`: rol sin permiso.
- `404`: persona/cita/regla inexistente o inactiva.
- `409`: la cita ya consumió inventario.
- `422`: payload inválido, regla duplicada, sin reglas, producto inactivo o stock insuficiente.

### Política final de recepcionista
- Recepcionista puede seguir usando productos y movimientos de inventario base.
- Recepcionista no puede crear/editar/eliminar reglas de consumo por servicio.
- Recepcionista sí puede ejecutar consumo automático por cita.
- Dentista no gestiona inventario ni consumo automático por cita.
