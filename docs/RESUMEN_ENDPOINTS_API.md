# Resumen de Endpoints de la API

Este documento es un índice rápido de los principales endpoints expuestos por el backend de Laravel, basándose en la configuración actual de rutas (`php artisan route:list`).

> **Nota General:** Salvo la ruta `/api/login`, todas las demás rutas requieren que se incluya el header `Authorization: Bearer {token}` (Autenticación Sanctum) y que la cuenta del empleado tenga el estado activo (`EnsureEmpleadoIsActive`).

| Módulo | Método | Ruta | Auth | Roles | Body principal (JSON) | Respuesta (OK) | Notas |
|---|---|---|---|---|---|---|---|
| **Seguridad** | POST | `/api/login` | No | - | `{correoElectronico, password}` | 200 `{token, empleado}` | Inicio de sesión |
| | POST | `/api/logout` | Sí | - | - | 204 | Revoca token actual |
| | GET | `/api/me` | Sí | - | - | 200 `{empleado}` | Obtiene usuario actual |
| | POST | `/api/change-password` | Sí | - | `{current_password, new_password, new_password_confirmation}` | 200 | Actualiza contraseña |
| **Personas** | GET | `/api/personas` | Sí | admin, recep | - | 200 | Lista pacientes activos |
| | POST | `/api/personas` | Sí | admin, recep | `{nombre, apellidoP, celular, correo...}` | 201 | Crea paciente |
| | GET | `/api/personas/{id}` | Sí | admin, recep | - | 200 | 404 si inactivo |
| | PUT/PATCH| `/api/personas/{id}` | Sí | admin, recep | `{nombre, celular, correo...}` | 200 | Actualiza paciente |
| | DELETE | `/api/personas/{id}` | Sí | admin, recep | - | 204 | Baja lógica (estado=false) |
| **Caja (Pagos)**| POST | `/api/pagos` | Sí | admin, recep | `{total, efectivo, tarjeta}` | 201 | Debe estar liquidado |
| | PUT/PATCH| `/api/pagos/{id}` | Sí | admin, recep | `{efectivo, tarjeta, ...}` | 200 | Prohibido si corte cerrado |
| **Caja (Cortes)**| GET | `/api/cortes` | Sí | admin, recep | - | 200 | Historial de cortes |
| | GET | `/api/cortes/activo` | Sí | admin, recep | - | 200 | Corte del turno actual |
| | POST | `/api/cortes` | Sí | admin, recep | - | 201 | Solo un corte activo a la vez |
| | PUT/PATCH| `/api/cortes/{id}` | Sí | admin, recep | `{fechaFin}` | 200 | Cierra el corte calculando totales |
| **Comprobantes**| GET | `/api/comprobantes` | Sí | admin, recep | - | 200 | Lista recibos internos activos |
| | POST | `/api/comprobantes` | Sí | admin, recep | `{idPago, observaciones}` | 201 | Crea folio y congela montos |
| | GET | `/api/comprobantes/{id}`| Sí | admin, recep | - | 200 | Detalle del recibo |
| | DELETE | `/api/comprobantes/{id}`| Sí | admin, recep | - | 204 | Cancela recibo (no el pago) |
| **Inv. Productos**| GET | `/api/inventario/productos` | Sí | admin, recep | - | 200 | Lista catálogo activo |
| | POST | `/api/inventario/productos` | Sí | admin, recep | `{nombre, unidad, stockInicial}` | 201 | Crea producto y mov. inicial |
| | GET | `/api/inventario/productos/{id}`| Sí | admin, recep | - | 200 | Consulta detalle (bajoStock) |
| | PUT/PATCH| `/api/inventario/productos/{id}`| Sí | admin, recep | `{nombre, descripcion...}` | 200 | No altera stock directamente |
| | DELETE | `/api/inventario/productos/{id}`| Sí | admin, recep | - | 204 | Baja lógica. Retorna 404. |
| **Inv. Movimientos**| GET | `/api/inventario/movimientos` | Sí | admin, recep | - | 200 | Historial de stock |
| | POST | `/api/inventario/movimientos` | Sí | admin, recep | `{idProducto, tipo, cantidad}` | 201 | entrada/salida/ajuste |
| **Empleados** | GET | `/api/empleados` | Sí | admin | - | 200 | Usuarios del sistema |
| | POST | `/api/empleados` | Sí | admin | `{datos, idRol...}` | 201 | Crea usuario |
| | PUT/PATCH| `/api/empleados/{id}` | Sí | admin | `{datos...}` | 200 | Actualiza |
| | DELETE | `/api/empleados/{id}` | Sí | admin | - | 204 | Baja, revoca tokens |
| **Agenda (Citas)**| GET | `/api/citas` | Sí | admin, recep, dentista | - | 200 | Lista agenda |
| | POST | `/api/citas` | Sí | admin, recep | `{idEmpleado, fecha...}` | 201 | Valida traslapes 422 |
| | PUT/PATCH| `/api/citas/{id}` | Sí | admin, recep, dentista | `{datos de agenda}` | 200 | Valida traslapes y excluye editado |
| | DELETE | `/api/citas/{id}` | Sí | admin, recep | - | 204 | Elimina cita |
| **Servicios** | GET | `/api/servicios` | Sí | admin, recep, dentista | - | 200 | Catálogo de tratamientos |
| | POST | `/api/servicios` | Sí | admin | `{nombre, duracion, costo...}` | 201 | Crea servicio |
| **Recetas** | GET | `/api/recetas` | Sí | admin, dentista | - | 200 | Listado |
| | POST | `/api/recetas` | Sí | admin, dentista | `{idCita, prescripcion...}` | 201 | Solo un recetario por cita |

*Este resumen omite rutas adicionales de catálogos simples como `clases-servicio` o `tipos-empleado`, que responden con GET clásicos.*
