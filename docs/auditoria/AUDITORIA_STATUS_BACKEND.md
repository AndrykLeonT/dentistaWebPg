# Auditoría ejecutable del backend dental

Fecha de ejecución: 2026-05-26  
Proyecto auditado: backend REST Laravel para consultorio dental  
Alcance: ejecución, inspección y reporte. No se modificó código fuente ni la base principal `dentista_db`.

## 1. Resumen ejecutivo

El backend arranca correctamente, registra 51 rutas API y su suite existente pasa por completo cuando se ejecuta de forma segura contra una base separada: **54 pruebas aprobadas y 106 aserciones** en `dentista_db_testing`.

La implementación cubre autenticación con Laravel Sanctum, roles, catálogos, personas, empleados, citas, recetas, pagos y cortes. Los endpoints CRUD de `tipos-empleado` y `clases-servicio`, cuya resolución por ID era un punto a validar, **funcionan en prueba ejecutada** (`show`, `update` y `destroy` respondieron exitosamente).

Sin embargo, las pruebas adicionales controladas confirman fallas bloqueantes:

- Un empleado desactivado conserva acceso mediante un token emitido previamente.
- Se aceptan pagos incompletos o excedidos marcándolos como pagados.
- Los totales de un corte cerrado pueden modificarse y sus pagos pueden moverse o alterarse después del cierre.
- Una cita puede editarse para colisionar con otra cita activa.
- Una persona dada de baja lógica sigue visible mediante URL directa.

### Veredicto final

| Uso previsto | Veredicto | Justificación |
|---|---|---|
| Listo para demo técnica con datos ficticios y base aislada | **Sí, condicionado** | La suite existente pasa; los escenarios críticos deben evitarse o explicarse durante la demo. |
| Listo para integración frontend formal | **No** | El frontend podría consumir endpoints, pero integraría comportamientos incorrectos en sesión, agenda y caja. |
| Listo para datos reales | **No** | Hay fallas confirmadas de autorización posterior a baja e integridad financiera. |
| Listo para producción | **No** | Además de lo anterior, faltan políticas de token, endurecimiento, contrato API y estrategia de testing estable. |

## 2. Entorno verificado

| Componente | Resultado verificado | Evidencia |
|---|---|---|
| PHP | 8.1.10 CLI, ZTS x64 | `php -v` |
| Composer | 2.4.1 | `composer --version` mediante `composer.phar` de Laragon |
| Laravel | 10.50.0 | `php artisan --version`, `artisan about`, `composer show laravel/framework` |
| Laravel Sanctum | 3.3.3 instalado | `composer show laravel/sanctum` |
| PHPUnit | 10.5.63 instalado | `composer show phpunit/phpunit` |
| MySQL | MySQL Community Server 8.0.30; conexión Laravel operativa | cliente MySQL y `php artisan migrate:status` |
| Driver PHP MySQL | `pdo_mysql` y `mysqli` habilitados | `php -m` |
| Base principal | `dentista_db` existe; 16 migraciones reportadas como `Ran` | consulta de esquemas y `migrate:status` sin escritura |
| Base de pruebas inicial | `dentista_db_testing` no existía | `SHOW DATABASES LIKE 'dentista_db%'` |
| Base usada para pruebas | `dentista_db_testing`, creada durante esta auditoría | creación explícita y `config:show database.connections.mysql` con contraseña omitida en este reporte |
| Entorno Laravel | `local`, `APP_DEBUG` habilitado | `artisan about` |

### Preparación segura realizada

`phpunit.xml` establece `APP_ENV=testing`, pero no fija `DB_DATABASE`; por defecto las pruebas heredarían la base configurada en `.env`. Como las suites feature usan `RefreshDatabase`, no se ejecutó `php artisan test` contra la configuración predeterminada.

Se realizó únicamente lo siguiente sobre MySQL:

1. Se consultó `dentista_db` en modo lectura mediante `migrate:status`.
2. Se creó `dentista_db_testing` con codificación `utf8mb4`.
3. Para cada comando destructivo o de prueba se definieron variables temporales del proceso:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='dentista_db_testing'
```

4. Se ejecutó `artisan migrate:fresh --database=mysql --force` únicamente con `DB_DATABASE=dentista_db_testing`.

No se ejecutó `migrate:fresh`, `db:wipe`, `migrate:refresh` ni pruebas con `RefreshDatabase` sobre `dentista_db`.

## 3. Comandos ejecutados

| Comando o acción | Resultado | Observaciones |
|---|---|---|
| `php -v` | Exitoso | PHP 8.1.10. |
| `composer --version` | Exitoso | Composer 2.4.1 ejecutado con el PHP/PHAR de Laragon. |
| `composer show laravel/framework` | Exitoso | Laravel `v10.50.0`. |
| `composer show laravel/sanctum` | Exitoso | Sanctum `v3.3.3`. |
| `composer show phpunit/phpunit` | Exitoso | PHPUnit `10.5.63`. |
| `php artisan --version` | Exitoso | Laravel Framework 10.50.0. |
| `php artisan about` | Exitoso | MySQL configurado, debug habilitado, entorno local. |
| `php artisan route:list --path=api` | Exitoso | 51 rutas API. |
| `php artisan route:list --json --path=api` | Exitoso | Permitió comprobar middleware por endpoint. |
| `php artisan migrate:status` sobre `dentista_db` | Exitoso, lectura | 16 migraciones aplicadas. |
| Consulta `SHOW DATABASES LIKE 'dentista_db%'` | Exitoso | Inicialmente sólo existía `dentista_db`. |
| `CREATE DATABASE IF NOT EXISTS dentista_db_testing ...` | Exitoso | Única base creada para aislar pruebas. |
| `artisan config:show database.connections.mysql` con override testing | Exitoso | Confirmó que el proceso apuntaba a `dentista_db_testing`; secretos no se reproducen aquí. |
| `artisan db:show --database=mysql` con override testing | No ejecutable | Solicita instalar Doctrine DBAL; no se instaló nada. |
| `artisan migrate:fresh --database=mysql --force` con override testing | Exitoso | Ejecutado únicamente sobre `dentista_db_testing`. |
| `php artisan test` con override testing | Exitoso | 54/54 pruebas pasan, 106 aserciones, 5.30 s. |
| Suites feature individuales con override testing | Exitoso | Resultados detallados en la sección 4. |
| Solicitudes controladas vía kernel HTTP sobre testing | Exitoso | Validó casos no cubiertos por la suite existente. |
| Consulta a `information_schema` sobre testing | Exitoso | Inventario de tablas, foráneas e índices. |
| Búsqueda de `DB::transaction()` en `app/` | Sin coincidencias | No se encontraron transacciones de aplicación. |

Una primera ejecución ad hoc vía kernel HTTP fue descartada porque reutilizaba un guard autenticado entre solicitudes en el mismo proceso, invalidando resultados negativos de autorización. Se reinicializó únicamente `dentista_db_testing` y se repitió la batería limpiando guards antes de cada petición; sólo la segunda ejecución se utiliza como evidencia funcional.

## 4. Resultado de pruebas automatizadas

### Suite completa existente

| Ejecución | Pasan | Fallan | Aserciones | Tiempo | Base |
|---|---:|---:|---:|---:|---|
| `php artisan test` | 54 | 0 | 106 | 5.30 s | `dentista_db_testing` |

### Suites solicitadas

| Suite | Pasan | Fallan | Error | Causa probable si falla | Prioridad | Tiempo |
|---|---:|---:|---|---|---|---:|
| `AuthTest` | 9 | 0 | Ninguno | N/A | N/A | 2.15 s |
| `PersonaTest` | 8 | 0 | Ninguno | N/A | N/A | 2.04 s |
| `EmpleadoTest` | 9 | 0 | Ninguno | N/A | N/A | 2.19 s |
| `CitaTest` | 7 | 0 | Ninguno | N/A | N/A | 5.59 s |
| `RecetaTest` | 7 | 0 | Ninguno | N/A | N/A | 3.17 s |
| `PagoTest` | 5 | 0 | Ninguno | N/A | N/A | 2.01 s |
| `CorteTest` | 7 | 0 | Ninguno | N/A | N/A | 2.38 s |
| Otros (`ExampleTest` unit/feature) | 2 | 0 | Ninguno | N/A | N/A | Incluidos en suite completa |

### Cobertura real de la suite

La suite automatizada valida login normal, roles principales, CRUD parcial, filtros de citas, colisión al crear cita, una receta por cita a nivel de validación, pago con corte abierto y totalización inicial de corte.

La suite no detecta las fallas confirmadas posteriormente: token después de baja, colisión en actualización, visibilidad por URL de registros inactivos, pagos inconsistentes y mutabilidad de cortes cerrados.

## 5. Status por módulo

| Módulo | Estado | Evidencia ejecutada | Riesgo actual | Acción recomendada |
|---|---|---|---|---|
| Autenticación | Amarillo | Login activo `200`; incorrecto/inexistente/inactivo `401`; token válido `/me` `200`; inválido/sin token `401`; logout revoca token usado | Token previo no se invalida al desactivar empleado; sin expiración | Resolver hallazgos SEC-001 y SEC-002. |
| Roles/permisos | Amarillo | Dentista a cortes `403`; recepcionista a recetas `403`; rutas positivas funcionaron con roles permitidos | Empleado desactivado mantiene rutas de su rol con token previo | Integrar estado activo a autorización. |
| Tipos de empleado | Verde | Crear `201`, ver `200`, actualizar `200`, baja `204` | No se confirmó defecto de binding | Mantener pruebas automatizadas CRUD para prevenir regresión. |
| Clases de servicio | Verde | Crear `201`, ver `200`, actualizar `200`, baja `204` | No se confirmó defecto de binding | Agregar la cobertura a suite estable. |
| Personas | Amarillo | CRUD y búsqueda funcionan; suite 8/8 | Después de baja, `GET /api/personas/{id}` devolvió `200` | Definir y aplicar política de visibilidad de inactivos. |
| Servicios | Verde/Amarillo | Crear/listar/actualizar/baja respondieron `201/200/200/204` | No hay prueba automatizada propia; patrón de baja directa requiere revisión equivalente | Agregar tests CRUD e inactividad. |
| Empleados | Rojo | Suite 9/9; hash y reset cubiertos | Baja no revoca token: acceso posterior confirmado | Bloqueante antes de datos reales. |
| Citas | Rojo | Alta y colisión al crear funcionan (`201`, duplicado `422`) | Actualizar segunda cita al horario ocupado devolvió `200` | Bloqueante para agenda confiable. |
| Recetas | Amarillo | Dentista crea `201`; duplicado por cita devuelve `422`; suite 7/7 | Unicidad está en request, no en índice de BD | Reforzar invariantes y probar concurrencia. |
| Pagos | Rojo | Flujo esperado de suite 5/5; casos adversos ejecutados | Pago incompleto/excedido/cero aceptados; incompleto queda `pagado=true` | Bloqueante para caja/datos reales. |
| Cortes | Rojo | Apertura, rechazo de segundo abierto y cierre inicial funcionan | Corte cerrado permite editar totales y alterar/mover pagos | Bloqueante para caja/datos reales. |
| Base de datos | Amarillo | 16 migraciones aplicadas; foráneas verificadas | Faltan restricciones para invariantes críticas | Agregar diseño de integridad antes de producción. |
| Documentación | Amarillo | `RESUMEN_PROYECTO.md` describe API y Sanctum | `README.md` declara Laravel 11, pero corre Laravel 10.50.0; no se encontró OpenAPI/Postman vigente en el árbol actual | Actualizar contrato e instalación. |
| Testing | Amarillo | Suite completa pasa sobre base aislada | `phpunit.xml` no fuerza base testing; ejecución descuidada puede apuntar a la principal | Establecer configuración segura de testing/CI. |

## 6. Hallazgos críticos

### SEC-001 - Tokens válidos después de desactivar empleado

| Campo | Detalle |
|---|---|
| Descripción | El empleado desactivado conserva autorización con un token emitido antes de la baja. |
| Evidencia de código | `app/Http/Controllers/EmpleadoController.php:82-86` sólo actualiza `estado`; `app/Http/Middleware/CheckRol.php:17-34` no rechaza empleados inactivos. |
| Prueba realizada | Login del empleado, baja vía `DELETE /api/empleados/{id}` con admin, reutilización del token en `/api/me` y `POST /api/personas`. |
| Resultado | Baja `204`; `/api/me` posterior `200`; creación posterior con rol del empleado desactivado `201`. |
| Impacto | Una cuenta suspendida puede continuar accediendo y modificando datos personales/operativos. |
| Recomendación | Revocar tokens al desactivar y verificar estado activo en cada petición autenticada. |
| Prioridad | **P0 - Crítica** |

### FIN-001 - Pagos inconsistentes son aceptados y marcados pagados

| Campo | Detalle |
|---|---|
| Descripción | No se valida que `efectivo + tarjeta` corresponda al `total`; el alta fuerza `pagado=true`. |
| Evidencia de código | `app/Http/Requests/StorePagoRequest.php:14-21`; `app/Http/Controllers/PagoController.php:20-38`; `app/Http/Resources/PagoResource.php:12-36`. |
| Prueba realizada | Alta con total `500`, cobro `100+100`; alta con total `100`, cobro `200+0`; alta con total `0`; alta con total negativo. |
| Resultado | Incompleto: `201`, `pagado=true`, `pendiente=300`; excedido: `201`, `pendiente=-100`; total cero: `201`; negativo: `422`. |
| Impacto | Saldos y reportes de caja no representan cobros reales. |
| Recomendación | Definir pago parcial/total y validar o derivar `pagado` según regla aprobada. |
| Prioridad | **P0 - Crítica** |

### FIN-002 - Corte cerrado no es inmutable

| Campo | Detalle |
|---|---|
| Descripción | Después de cerrar un corte, pueden sustituirse totales y cambiar movimientos asociados. |
| Evidencia de código | `app/Http/Requests/UpdateCorteRequest.php:14-22`; `app/Http/Controllers/CorteController.php:53-70`; `app/Http/Requests/UpdatePagoRequest.php:14-22`. |
| Prueba realizada | Abrir corte, registrar pagos, cerrar, enviar nuevos `tEfectivo/tTarjeta`, abrir otro corte y mover/editar un pago original. |
| Resultado | Totales calculados al cierre `300.00/100.00`; actualización posterior `200` y totales quedaron `999.00/888.00`; mover pago y alterar monto respondieron `200`. |
| Impacto | Pérdida de trazabilidad e integridad contable. |
| Recomendación | Bloquear modificación posterior o implementar ajustes auditados e invariantes transaccionales. |
| Prioridad | **P0 - Crítica** |

### CIT-001 - Colisión de citas permitida en actualización

| Campo | Detalle |
|---|---|
| Descripción | La regla que evita cita duplicada se aplica al crear, pero no al actualizar. |
| Evidencia de código | `StoreCitaRequest.php:27-41` implementa colisión; `UpdateCitaRequest.php:14-23` no contiene regla equivalente. |
| Prueba realizada | Crear citas para el mismo servicio/fecha a `10:00` y `11:00`; actualizar la segunda a `10:00`. |
| Resultado | El duplicado en alta devolvió `422`; la colisión por actualización devolvió `200`. |
| Impacto | Agenda con reservas simultáneas incompatibles. |
| Recomendación | Aplicar la regla a actualizaciones y respaldarla con estrategia de concurrencia/índice acorde al modelo de disponibilidad. |
| Prioridad | **P0 - Crítica para operación de agenda** |

### DATA-001 - Baja lógica visible por URL directa

| Campo | Detalle |
|---|---|
| Descripción | Un registro que dejó de aparecer en listados activos puede seguir consultándose por su ID. |
| Evidencia de código | `PersonaController::index()` usa `Persona::activos()`; `show(Persona $persona)` no verifica `estado`. El patrón se repite en varios controladores. |
| Prueba realizada | Crear persona, eliminarla lógicamente mediante API y solicitar `GET /api/personas/{id}`. |
| Resultado | Alta `201`, baja `204`, consulta posterior `200`. |
| Impacto | Exposición o uso accidental de registros dados de baja; comportamiento inconsistente del API. |
| Recomendación | Definir regla de negocio para inactivos y cubrir todos los recursos con pruebas. |
| Prioridad | **P0/P1 según política de privacidad** |

## 7. Hallazgos medios y bajos

### SEC-002 - Tokens Sanctum sin expiración

| Campo | Detalle |
|---|---|
| Descripción | La configuración no impone vencimiento a tokens personales. |
| Evidencia | `config/sanctum.php:49` contiene `'expiration' => null`. |
| Prueba realizada | Lectura de configuración durante batería controlada. |
| Resultado | `sanctum_expiration = null`. |
| Impacto | Un token filtrado permanece utilizable hasta revocación manual. |
| Recomendación | Definir vigencia y estrategia de revocación/rotación. |
| Prioridad | Alta |

### API-001 - Binding de `tipos-empleado` verificado correctamente

| Campo | Detalle |
|---|---|
| Descripción | Se validó el posible riesgo de resolución por ID. No se reprodujo defecto. |
| Evidencia | Rutas CRUD registradas y ejecución sobre testing. |
| Prueba realizada | `POST`, `GET /{id}`, `PUT /{id}`, `DELETE /{id}` con token admin. |
| Resultado | `201`, `200`, `200`, `204`. |
| Recomendación | Añadir estos casos a pruebas automatizadas permanentes. |
| Prioridad | Sin defecto confirmado; mejora de cobertura |

### API-002 - Binding de `clases-servicio` verificado correctamente

| Campo | Detalle |
|---|---|
| Descripción | Se validó el posible riesgo de resolución por ID. No se reprodujo defecto. |
| Evidencia | Rutas CRUD registradas y ejecución sobre testing. |
| Prueba realizada | `POST`, `GET /{id}`, `PUT /{id}`, `DELETE /{id}` con token admin. |
| Resultado | `201`, `200`, `200`, `204`. |
| Recomendación | Añadir estos casos a pruebas automatizadas permanentes. |
| Prioridad | Sin defecto confirmado; mejora de cobertura |

### DB-001 - Invariantes sin restricción única en base de datos

| Campo | Detalle |
|---|---|
| Descripción | La base no protege receta única por cita, colisión de agenda ni corte abierto único. |
| Evidencia | Consulta a `information_schema.statistics` en `dentista_db_testing`: sólo se hallaron índices únicos de usuario, RFC, correo, token y elementos del framework. |
| Impacto | Solicitudes concurrentes pueden eludir validaciones de request. |
| Recomendación | Definir invariantes y diseñar restricciones/locking apropiados. |
| Prioridad | Alta/Media |

### TX-001 - Operaciones compuestas sin transacciones de aplicación

| Campo | Detalle |
|---|---|
| Descripción | No se encontraron llamadas a `DB::transaction()` en `app/`. |
| Evidencia | Búsqueda estática; creación de empleado crea `Persona` y luego `Empleado` en `EmpleadoController.php:22-47`. |
| Impacto | Una falla intermedia puede dejar persona huérfana; cierres/cambios financieros carecen de garantía atómica de negocio. |
| Prueba realizada | Inspección; no se inyectaron fallas artificiales porque requeriría prueba adicional o modificación temporal no autorizada. |
| Prueba a agregar | Provocar fallo del segundo insert de empleado y afirmar que no persiste la persona; pruebas concurrentes de pago/cierre. |
| Prioridad | Alta/Media |

### TST-001 - Testing exitoso, pero no aislado por configuración del repositorio

| Campo | Detalle |
|---|---|
| Descripción | Las pruebas pasan con override temporal, pero `phpunit.xml` no fija una base de pruebas. |
| Evidencia | `phpunit.xml:21-30`; pruebas feature usan `RefreshDatabase`. |
| Resultado | Ejecución segura posible sólo tras crear base separada y fijar `DB_DATABASE=dentista_db_testing` por proceso. |
| Impacto | Riesgo operativo de refrescar la base local principal si se ejecutan pruebas sin preparación. |
| Recomendación | Configurar formalmente el entorno testing y documentar el comando seguro. |
| Prioridad | Alta |

### DOC-001 - Documentación inconsistente

| Campo | Detalle |
|---|---|
| Descripción | La versión de Laravel no coincide entre documentación y aplicación ejecutada. |
| Evidencia | `README.md:4` indica Laravel 11; `composer show` y `artisan about` indican Laravel 10.50.0; `RESUMEN_PROYECTO.md:5` sí indica Laravel 10. |
| Impacto | Confusión durante instalación, soporte e integración. |
| Recomendación | Consolidar instalación, testing, roles y contrato API vigente. |
| Prioridad | Media |

## 8. Integridad de base de datos

### Tablas observadas en `dentista_db_testing`

| Tipo | Tablas |
|---|---|
| Dominio | `personas`, `tipo_empleados`, `empleados`, `clase_servicios`, `servicios`, `citas`, `recetas`, `cortes`, `pagos` |
| Framework/infraestructura | `users`, `password_reset_tokens`, `failed_jobs`, `personal_access_tokens`, `migrations` |

### Relaciones foráneas verificadas

| Tabla/columna | Referencia | ON DELETE | Comentario |
|---|---|---|---|
| `empleados.idPersona` | `personas.idPersona` | CASCADE | Única cascada observada. |
| `empleados.idTipoEmpleado` | `tipo_empleados.idTipoEmpleado` | NO ACTION | Impide eliminar físicamente tipo referenciado. |
| `servicios.idClaseServicio` | `clase_servicios.idClaseServicio` | NO ACTION | Consistente con modelo. |
| `citas.idPersona` | `personas.idPersona` | NO ACTION | Consistente con modelo. |
| `citas.idServicio` | `servicios.idServicio` | NO ACTION | Consistente con modelo. |
| `recetas.idCita` | `citas.idCita` | NO ACTION | Consistente con modelo. |
| `pagos.idPersona` | `personas.idPersona` | NO ACTION | Consistente con modelo. |
| `pagos.idEmpleado` | `empleados.idEmpleado` | NO ACTION | Consistente con modelo. |
| `pagos.idCorte` | `cortes.idCorte` | NO ACTION | Consistente con modelo. |

### Índices únicos observados y faltantes

| Invariante | Estado |
|---|---|
| Usuario de empleado único | Presente: `empleados_usuario_unique`. |
| RFC único | Presente: `empleados_rfc_unique`; la columna ahora permite `NULL`. |
| Correo de persona único | Presente: `personas_correoelectronico_unique`; la columna permite `NULL`. |
| Token Sanctum único | Presente. |
| Una receta por cita | No existe índice único; sólo validación de request. |
| Colisión de citas | No existe índice/restricción; además el update permite duplicación. |
| Un corte activo abierto | No existe restricción de base; sólo consulta previa en controller. |

### Riesgos por baja lógica

El patrón `estado=false` está presente en modelos de dominio. Los listados aplican scopes activos en varios módulos, pero la consulta directa por route model binding no filtra estado. Se confirmó el problema en personas; por inspección el patrón merece pruebas equivalentes en citas, empleados, servicios, recetas, pagos y catálogos.

## 9. Seguridad

| Tema | Estado verificado | Evaluación |
|---|---|---|
| Autenticación | Sanctum Bearer sobre modelo `Empleado`; login activo y rechazos básicos correctos | Base funcional |
| Logout | Elimina el token utilizado; token después de logout devolvió `401` | Correcto para sesión actual |
| Baja de empleado | Token previo mantiene `/api/me` y operaciones por rol | Crítico |
| Expiración | `null` | Alto riesgo si se filtra un token |
| Roles | Permisos negativos probados: dentista/cortes y recepcionista/recetas devuelven `403` | Correcto para cuentas activas |
| Datos personales | Rutas autenticadas de personas/empleados exponen datos de contacto; cualquier rol autenticado tiene lecturas según rutas | Debe aprobarse bajo mínimo privilegio |
| `APP_DEBUG` | Habilitado en entorno local | Correcto sólo para desarrollo; bloquear en producción |
| CORS | Configura origen por `FRONTEND_URL` y credenciales habilitadas | Requiere valores específicos por entorno |
| Rate limit | API configurada a 60 solicitudes/minuto por usuario o IP | Medida positiva presente |
| Credenciales/secretos | No se incluyeron tokens, `APP_KEY` ni contraseñas en este reporte | Cumplido |

## 10. Integración con frontend

### Endpoints disponibles

La API registra 51 rutas. `POST /api/login` es pública. `POST /api/logout`, `GET /api/me` y `POST /api/change-password` usan `auth:sanctum`. Los módulos CRUD existen para tipos de empleado, clases de servicio, personas, servicios, empleados, citas, recetas, pagos y cortes.

| Rol | Operaciones principales verificadas |
|---|---|
| Admin | Catálogos y acciones administrativas; pruebas de catálogo exitosas. |
| Recepcionista | Personas, servicios, citas, pagos y cortes; flujos ejecutados exitosamente salvo reglas defectuosas documentadas. |
| Dentista | Recetas; creación verificada, acceso indebido a cortes rechazado. |

### Endpoints riesgosos para integrar

| Endpoint/flujo | Riesgo |
|---|---|
| Sesión con usuario dado de baja | El frontend no puede confiar en que una baja invalide sesiones. |
| `PUT /api/citas/{id}` | Puede producir colisión de agenda. |
| `POST/PUT /api/pagos` | Permite estados monetarios incoherentes. |
| `PUT /api/cortes/{id}` | Permite cambiar total de corte cerrado. |
| `GET /api/personas/{id}` tras baja | Puede retornar datos dados de baja. |

### Contrato y nomenclatura

- No se encontró una especificación OpenAPI/Swagger o colección Postman vigente en el árbol actual.
- La API usa nombres camelCase y campos con caracteres no ASCII, por ejemplo `contraseña`, `cambioContraseña` y `nuevaContraseña`.
- El frontend debe tratar respuestas JSON de éxito `200`, creación `201`, baja `204`, no autenticado `401`, rol denegado `403`, no encontrado `404` y validación/regla `422`.
- Antes de formalizar integración se debe acordar si esos nombres con `ñ` forman parte permanente del contrato.

### Validaciones observadas

| Área | Validación presente | Vacío comprobado o relevante |
|---|---|---|
| Empleados | Usuario único, existencia de tipo, contraseña mínima y confirmación en reset | Sesiones previas no revocadas al desactivar |
| Personas | Requeridos, correo y longitudes | Baja visible por ID |
| Servicios | Clase existente, costo no negativo y duración con formato | Cobertura automatizada faltante |
| Citas | Referencias, fechas/horas y colisión en alta | Colisión ausente en actualización |
| Recetas | Cita existente y `unique:recetas,idCita` en alta | Sin índice único de base |
| Pagos | Montos numéricos no negativos y persona existente | Sin igualdad/consistencia de cobro ni derivación de `pagado` |
| Cortes | Fondo no negativo; fecha fin y campos numéricos | Permite totales derivados manuales después de cierre |

## 11. Veredicto final

### Qué sí está bien

- El backend Laravel funciona y se conecta a MySQL.
- La API está estructurada por módulos reconocibles y registra sus rutas.
- Sanctum y la matriz básica de roles operan para los escenarios contemplados.
- La suite existente es ejecutable y pasa completamente en una base de pruebas aislada.
- Los bindings de `tipos-empleado` y `clases-servicio` funcionan en CRUD ejecutado.
- Las relaciones foráneas principales existen y coinciden con las relaciones modeladas.

### Qué no está listo

- La seguridad de sesiones no es correcta frente a baja de empleados.
- La caja no mantiene invariantes confiables.
- La agenda permite choques mediante actualización.
- La semántica de baja lógica no es consistente en consultas directas.
- El entorno de testing no está aislado por configuración versionada.
- La documentación no es un contrato completo ni consistente con la versión ejecutada.

### Qué bloquea la entrega como backend estable

1. SEC-001: token válido después de desactivar empleado.
2. FIN-001/FIN-002: pagos incoherentes y cortes cerrados editables.
3. CIT-001: colisión de citas en actualización.
4. TST-001: riesgo de ejecutar pruebas contra la base principal sin override seguro.

### Qué bloquea datos reales

Los cuatro puntos anteriores, además de definir política de acceso a datos personales, historial de cambios y manejo de registros inactivos.

### Qué bloquea producción

Todo lo anterior, más expiración/revocación formal de tokens, configuración de producción sin debug, contrato API formal, CI con base aislada y procedimientos operativos de respaldo/observabilidad.

## 12. Lista priorizada de acciones

### P0 - Obligatorio antes de entregar backend como estable

1. Invalidar acceso de empleados inactivos y revocar tokens existentes al desactivar credenciales.
2. Definir e implementar reglas contables para pagos, totales derivados e inmutabilidad/auditoría de cortes cerrados.
3. Impedir colisiones de citas tanto en alta como en actualización, considerando concurrencia.
4. Definir el comportamiento de bajas lógicas en endpoints directos y cubrirlo con pruebas.
5. Configurar una ejecución de tests que siempre use `dentista_db_testing` o infraestructura equivalente aislada.

### P1 - Necesario antes de integrar frontend formalmente

1. Publicar contrato OpenAPI o colección Postman verificada para las 51 rutas.
2. Acordar campos del contrato con caracteres como `ñ` y documentar respuestas/errores.
3. Añadir pruebas automatizadas permanentes para catálogos, escenarios críticos ejecutados en esta auditoría y permisos de datos personales.
4. Definir expiración, renovación y almacenamiento frontend de tokens Sanctum.
5. Alinear README, instrucciones Laragon, migraciones, seeders, usuario inicial y proceso de pruebas con Laravel 10.50.0.

### P2 - Mejoras recomendadas

1. Revisar transacciones para operaciones compuestas y pruebas de fallas parciales/concurrencia.
2. Evaluar paginación, filtros y auditoría de cambios para operación real.
3. Retirar o documentar componentes residuales del esqueleto Laravel (`users`, `User`, vista inicial) si no forman parte del producto.
4. Preparar configuración productiva, logs, backups y monitoreo.

## Anexo: pruebas adicionales que deben incorporarse a la suite

| Prueba propuesta | Resultado que debería exigir |
|---|---|
| Token de empleado tras `DELETE /api/empleados/{id}` | Toda ruta protegida devuelve `401`/`403`. |
| Pago parcial/excedido/cero según regla aprobada | Rechazo o estado derivado coherente; nunca `pagado=true` incorrecto. |
| Modificación de corte cerrado | Rechazo o flujo de ajuste auditable. |
| Movimiento/edición de pago perteneciente a corte cerrado | Rechazo o ajuste controlado. |
| Update de cita hacia horario ocupado | `422` y cita original intacta. |
| `show` de recurso dado de baja | Respuesta definida, normalmente `404`, en todos los módulos. |
| Falla al crear empleado después de crear persona | No queda persona huérfana. |
| Solicitudes concurrentes de receta/corte/cita | Invariante preservada por aplicación/base. |
