Necesito que realices una REVISIÓN FINAL del backend Laravel y generes documentación técnica útil para que posteriormente otra IA o un desarrollador pueda implementar el frontend consumiendo la API existente.

IMPORTANTE:
Esta tarea es de revisión, documentación y verificación final. No implementes nuevas funcionalidades salvo que encuentres un error menor estrictamente necesario para que las pruebas pasen. Si encuentras problemas funcionales, documéntalos como pendientes; no abras nuevas fases ni refactorices módulos completos.

Contexto general del proyecto:
- Backend REST Laravel 10.50.0 para sistema de consultorio dental.
- Usa MySQL con Laragon.
- Usa Laravel Sanctum 3.3.3 con tokens Bearer.
- El usuario autenticable real del negocio es App\Models\Empleado.
- Las rutas protegidas usan:
  1. auth:sanctum
  2. empleado.activo
  3. rol:* cuando corresponde.
- La base no contiene datos reales.
- Puedes ejecutar migrate:fresh --seed si necesitas validar desde cero.
- Laragon está encendido y MySQL está disponible.
- Puedes usar dentista_db_testing o la base configurada localmente para pruebas.
- Documenta la base usada y los comandos ejecutados.

Fases ya realizadas y documentadas:
1. Fase 1 - Seguridad de autenticación y tokens.
   - Archivo esperado: docs/FASE_1_SEGURIDAD_TOKENS.md.
   - Se corrigió el uso de tokens por empleados inactivos.
   - Se agregó expiración Sanctum.
   - Se revocan tokens al desactivar/resetear empleado.

2. Fase 2 - Agenda y citas.
   - Archivo esperado: docs/FASE_2_AGENDA_CITAS.md.
   - Se corrigió disponibilidad de citas por dentista.
   - Se validan colisiones y traslapes por duración.
   - Este módulo pertenece a compañeros; no modificarlo salvo regresión.

3. Fase 3 - Caja, pagos y cortes.
   - Archivo esperado: docs/FASE_3_CAJA_PAGOS_CORTES.md.
   - Se corrigieron pagos incompletos, excedidos, total cero, campos manipulables y cortes cerrados.
   - Se agregó CajaService.

4. Fase 4 - Pacientes.
   - Archivo esperado: docs/FASE_4_PACIENTES.md.
   - Se corrigió baja lógica, visibilidad de inactivos y validación de correo.

5. Fase 5 - Facturación y comprobantes.
   - Archivo esperado: docs/FASE_5_FACTURACION.md.
   - Se creó módulo de comprobantes internos ligados a pagos.
   - No es facturación fiscal real.

6. Fase 6 - Inventario.
   - Archivo esperado: docs/FASE_6_INVENTARIO.md.
   - Se creó módulo de productos de inventario y movimientos de entrada, salida y ajuste.

Objetivo principal:
Generar una revisión final del backend y una documentación tipo instructivo para frontend, separada por módulos, con rutas, permisos, cuerpos de petición, respuestas, errores esperados y consideraciones de uso.

NO modificar estos módulos salvo que sea estrictamente necesario para documentación o pruebas:
- Usuarios/empleados.
- Citas/agenda.
- Servicios.
Esos módulos fueron trabajados por otros compañeros o ya quedaron funcionales para su flujo.

Módulos propios a revisar especialmente:
- Autenticación y seguridad.
- Pacientes/personas.
- Pagos y cortes.
- Facturación/comprobantes.
- Inventario.

También debes documentar, sin modificar, los módulos existentes que el frontend necesitará consumir:
- Empleados/usuarios.
- Citas.
- Servicios.
- Recetas si existe y está expuesta por API.
- Catálogos: tipos de empleado y clases de servicio.

Tareas obligatorias:

1. Revisar documentación existente
Lee y usa como contexto:
- docs/FASE_1_SEGURIDAD_TOKENS.md
- docs/FASE_2_AGENDA_CITAS.md
- docs/FASE_3_CAJA_PAGOS_CORTES.md
- docs/FASE_4_PACIENTES.md
- docs/FASE_5_FACTURACION.md
- docs/FASE_6_INVENTARIO.md

Si alguno no existe, documenta que falta.

2. Revisar código y rutas actuales
Inspecciona:
- routes/api.php
- app/Http/Controllers
- app/Http/Requests
- app/Http/Resources
- app/Models
- app/Services
- database/migrations
- tests/Feature

Genera inventario real de rutas usando:
- php artisan route:list --path=api -v

3. Ejecutar validación final
Ejecuta:
- php artisan config:clear
- php artisan cache:clear
- php artisan migrate:fresh --seed
- php artisan test
- php artisan route:list --path=api -v

Si usas dentista_db_testing o alguna base específica, documenta cuál fue.

4. No implementar nuevas funcionalidades
Si encuentras problemas:
- Si son críticos y rompen pruebas existentes, documenta y corrige únicamente si es mínimo.
- Si son mejoras o nuevas funcionalidades, NO las implementes.
- Regístralas como pendientes.

5. Generar documentación final en docs/
Debes crear estos archivos:

A) docs/REVISION_FINAL_BACKEND.md
B) docs/GUIA_FRONTEND_API.md

Opcional si lo consideras útil:
C) docs/RESUMEN_ENDPOINTS_API.md

============================================================
ARCHIVO A: docs/REVISION_FINAL_BACKEND.md
============================================================

Debe tener esta estructura:

# Revisión final del backend

## 1. Objetivo
Explicar que esta revisión valida el estado final del backend después de las fases realizadas.

## 2. Estado general
Indicar:
- Framework real.
- PHP.
- Base de datos.
- Autenticación.
- Número total de rutas API.
- Número final de pruebas y aserciones.
- Resultado de la suite.

## 3. Fases revisadas
Tabla:
| Fase | Documento | Estado | Observación |
|---|---|---|---|

Incluir:
- Fase 1 Seguridad.
- Fase 2 Agenda.
- Fase 3 Pagos/cortes.
- Fase 4 Pacientes.
- Fase 5 Comprobantes.
- Fase 6 Inventario.

## 4. Módulos propios
Separar:
- Autenticación y seguridad.
- Pacientes.
- Pagos y cortes.
- Comprobantes.
- Inventario.

Para cada uno:
- Estado: Verde / Amarillo / Rojo.
- Qué existe.
- Qué pruebas lo cubren.
- Pendientes.
- Riesgos.

## 5. Módulos de compañeros o fuera de alcance
Separar:
- Usuarios/empleados.
- Citas/agenda.
- Servicios.
- Recetas.
- Catálogos.

Para cada uno:
- Estado observado.
- Rutas existentes.
- Advertencia de no modificar sin coordinar.
- Consideraciones para frontend.

## 6. Resultado de pruebas
Tabla:
| Comando | Resultado | Observaciones |
|---|---|---|

Incluir:
- php artisan test.
- pruebas por filtros si se ejecutaron.
- route:list.
- migrate:fresh --seed.

## 7. Riesgos pendientes generales
Separar por prioridad:
- P0 si existe algo bloqueante.
- P1 para integración frontend.
- P2 para mejoras.

No inventes problemas. Basarte en código, reportes y pruebas.

## 8. Veredicto final
Indicar:
- Si está listo para demo técnica.
- Si está listo para integración frontend.
- Si está listo para datos reales.
- Si está listo para producción.
- Justificación breve.

## 9. Recomendaciones de continuidad
Indicar qué debería hacerse después, por ejemplo:
- Frontend.
- Manual de usuario.
- OpenAPI formal.
- PDF de comprobantes.
- Reportes.
- Backups.
- CORS/producción.
- Deploy.

============================================================
ARCHIVO B: docs/GUIA_FRONTEND_API.md
============================================================

Este archivo debe ser una guía práctica para que otra IA o desarrollador pueda crear el frontend consumiendo el backend.

Debe ser claro, modular y operativo.

Estructura obligatoria:

# Guía frontend para consumir la API

## 1. Propósito
Explicar que este documento instruye cómo consumir la API desde el frontend.

## 2. Base URL
Documentar la URL esperada localmente, por ejemplo:
- http://localhost:8000/api
o la que corresponda según php artisan serve / Laragon.

Si no puedes confirmarla, documentar:
- usar APP_URL o servidor local configurado;
- todas las rutas están bajo prefijo /api.

## 3. Autenticación
Explicar:
- Login.
- Token Bearer.
- Headers.
- Logout.
- Expiración.
- Qué hacer ante 401.
- Qué hacer ante 403.

Incluir ejemplo:

Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json

## 4. Códigos HTTP usados
Tabla:
| Código | Significado para frontend |
|---|---|
| 200 | Consulta/actualización correcta |
| 201 | Creación correcta |
| 204 | Baja lógica/cancelación sin contenido |
| 401 | No autenticado o token inválido |
| 403 | Rol sin permiso |
| 404 | Recurso inexistente o inactivo |
| 422 | Error de validación/regla de negocio |

## 5. Convenciones generales
Explicar:
- La mayoría de eliminaciones son bajas lógicas.
- No asumir eliminación física.
- Algunos recursos inactivos devuelven 404.
- Las respuestas son JSON.
- Los errores de validación devuelven 422.
- El frontend no debe enviar campos derivados.
- Campos con ñ deben respetarse si existen en el contrato actual.

## 6. Flujo mínimo de sesión frontend
Incluir pasos:
1. Login.
2. Guardar token.
3. Enviar token en cada petición.
4. Si llega 401, borrar token y redirigir a login.
5. Logout.
6. Manejar expiración de sesión.

## 7. Módulo: Autenticación y seguridad
Documentar endpoints:
- POST /login
- GET /me
- POST /logout
- POST /change-password

Para cada endpoint:
- Método.
- Ruta.
- Rol requerido.
- Body.
- Respuesta.
- Errores comunes.
- Notas frontend.

## 8. Módulo: Pacientes/personas
Documentar endpoints:
- GET /personas
- POST /personas
- GET /personas/{id}
- PUT/PATCH /personas/{id}
- DELETE /personas/{id}

Incluir:
- Campos esperados.
- Búsqueda si existe.
- Baja lógica.
- Inactivos devuelven 404.
- Validación de correo único.
- Permisos por rol.

## 9. Módulo: Pagos y cortes
Separar:

### Pagos
Endpoints:
- GET /pagos si existe.
- POST /pagos.
- GET /pagos/{id} si existe.
- PUT/PATCH /pagos/{id}.
- DELETE /pagos/{id}.

Documentar:
- El frontend NO envía idEmpleado.
- El frontend NO envía idCorte al crear.
- El frontend NO envía pagado.
- total = efectivo + tarjeta.
- No hay pagos parciales.
- Se requiere corte activo.
- Pagos de corte cerrado no se editan.

### Cortes
Endpoints:
- GET /cortes.
- POST /cortes.
- GET /cortes/activo si existe.
- GET /cortes/{id}.
- PUT/PATCH /cortes/{id}.
- DELETE /cortes/{id}.

Documentar:
- Sólo un corte activo.
- Cerrar corte calcula totales.
- El frontend no controla tEfectivo/tTarjeta.
- Corte cerrado es inmutable.

## 10. Módulo: Comprobantes internos
Endpoints:
- GET /comprobantes
- POST /comprobantes
- GET /comprobantes/{id}
- DELETE /comprobantes/{id}

Documentar:
- Es recibo interno, NO CFDI.
- Se emite desde idPago.
- Folio lo genera backend.
- Un comprobante por pago.
- Cancelación lógica.
- Cancelar comprobante no cancela pago.
- Puede emitirse para pago de corte cerrado si no altera datos financieros.

## 11. Módulo: Inventario
Separar:

### Productos de inventario
Endpoints:
- GET /inventario/productos
- POST /inventario/productos
- GET /inventario/productos/{id}
- PUT/PATCH /inventario/productos/{id}
- DELETE /inventario/productos/{id}

Documentar:
- stockInicial al crear.
- No editar stockActual directamente.
- bajoStock si existe.
- Baja lógica.
- Producto inactivo devuelve 404.

### Movimientos de inventario
Endpoints:
- GET /inventario/movimientos
- POST /inventario/movimientos

Documentar:
- tipoMovimiento: entrada, salida, ajuste.
- entrada suma.
- salida resta.
- ajuste establece stock físico.
- cantidad en ajuste significa nuevo stock total, no diferencia.
- stockAnterior, stockNuevo, idEmpleado y fechaMovimiento los controla backend.
- No permitir stock negativo.

## 12. Módulo: Citas/agenda
Documentar sin modificar:
- Endpoints existentes.
- idEmpleado es dentista.
- dentista puede venir null en históricos.
- Validación de traslapes.
- Frontend debe enviar idEmpleado al crear.
- No tocar lógica de este módulo sin coordinar con compañeros.

## 13. Módulo: Empleados/usuarios
Documentar sin modificar:
- Endpoints existentes.
- Roles.
- Admin/recepcionista/dentista.
- Inactivos.
- Reset password si existe.
- No tocar lógica sin coordinar con compañeros.

## 14. Módulo: Servicios
Documentar sin modificar:
- Endpoints existentes.
- precio/costo si ambos existen.
- activo/estado si ambos existen.
- No tocar lógica sin coordinar con compañeros.

## 15. Módulo: Recetas
Documentar endpoints existentes si existen:
- Listar.
- Crear.
- Ver.
- Actualizar.
- Eliminar.
- Roles permitidos.
- Relación con cita.

## 16. Catálogos
Documentar:
- Tipos de empleado.
- Clases de servicio.
- Rutas.
- Permisos.
- Uso esperado en formularios frontend.

## 17. Ejemplos de flujo frontend
Incluir ejemplos prácticos:

### Flujo login
1. POST /login.
2. Guardar token.
3. GET /me.

### Flujo crear paciente
1. POST /personas.
2. GET /personas.

### Flujo caja
1. POST /cortes para abrir corte.
2. POST /pagos para registrar pago.
3. POST /comprobantes para emitir recibo.
4. PUT/PATCH /cortes/{id} para cerrar corte, según ruta real.

### Flujo inventario
1. Crear producto.
2. Registrar entrada.
3. Registrar salida.
4. Registrar ajuste.
5. Consultar movimientos.

### Flujo agenda
1. Listar dentistas.
2. Crear cita con idEmpleado.
3. Manejar 422 por traslape.

## 18. Manejo de errores en frontend
Incluir recomendaciones:
- 401: cerrar sesión local.
- 403: mostrar “sin permiso”.
- 404: recurso no disponible.
- 422: mostrar errores de validación por campo.
- 500: mostrar error general y registrar.

## 19. Consideraciones para implementar frontend con otra IA
Esta sección debe servir para que otra IA prepare planes de frontend.

Incluir:
- Módulos de pantalla recomendados.
- Orden sugerido de implementación frontend.
- Componentes reutilizables:
  - cliente API.
  - auth store.
  - guards por rol.
  - formularios con errores 422.
  - tablas con filtros.
  - modales de confirmación.
- Rutas frontend sugeridas.
- Dependencias de datos.
- Campos controlados por backend que no deben enviarse.
- Campos que sí debe enviar el frontend.
- Riesgos al integrar.

## 20. Pendientes fuera de alcance
Separar:
- Facturación fiscal real.
- PDF de comprobantes.
- Envío por correo.
- Compras/proveedores.
- Lotes/caducidad.
- Consumo automático de inventario por servicio.
- Reportes avanzados.
- Deploy/producción.

============================================================
ARCHIVO C OPCIONAL: docs/RESUMEN_ENDPOINTS_API.md
============================================================

Si lo crees útil, genera una tabla compacta:

| Módulo | Método | Ruta | Auth | Roles | Body principal | Respuesta | Notas |
|---|---|---|---|---|---|---|---|

Este archivo debe servir como índice rápido de endpoints.

Criterios de aceptación:
1. No se deben implementar nuevas funcionalidades.
2. Se deben generar docs/REVISION_FINAL_BACKEND.md y docs/GUIA_FRONTEND_API.md.
3. Si se genera docs/RESUMEN_ENDPOINTS_API.md, debe ser consistente con route:list.
4. php artisan test debe pasar completo.
5. La guía frontend debe estar separada por módulos.
6. La guía frontend debe incluir URL base, autenticación, headers, códigos HTTP, endpoints, permisos, bodies, respuestas y errores.
7. La guía frontend debe indicar qué campos controla backend y cuáles debe enviar frontend.
8. La guía debe ser suficientemente clara para que otra IA pueda preparar planes de frontend.
9. No se deben modificar usuarios/empleados, citas, servicios ni otros módulos fuera de documentación.
10. Si encuentras inconsistencias, documentarlas como pendientes en REVISION_FINAL_BACKEND.md.

Entrega esperada:
Al finalizar, reporta:
- Archivos creados/modificados.
- Comandos ejecutados.
- Resultado de pruebas.
- Número final de pruebas y aserciones.
- Riesgos detectados.
- Veredicto final del backend.