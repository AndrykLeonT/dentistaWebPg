# Auditoria general del Sistema de Gestion Dental

Fecha de revision: 2026-05-26  
Alcance: codigo fuente propio, configuracion, rutas, migraciones, factories, seeders, pruebas y documentacion presente en el arbol de trabajo.

## Lectura rapida

El repositorio contiene un backend REST para consultorio dental desarrollado en Laravel 10.50.0 y PHP 8.1.10. No contiene un frontend funcional del producto: la unica ruta web entrega la pantalla predeterminada de Laravel, mientras que el negocio vive en 51 rutas `/api/*`.

La API esta estructurada con modelos Eloquent, controladores por recurso, `FormRequest` para validacion y `JsonResource` para respuestas. Incluye autenticacion mediante tokens personales de Laravel Sanctum y autorizacion basica por rol (`administrador`, `dentista`, `recepcionista`).

El proyecto esta en una etapa de backend implementado con pruebas escritas, pero no puede considerarse listo para entrega o produccion. La suite de negocio no puede ejecutarse en el ambiente revisado y se identificaron riesgos importantes de seguridad e integridad de datos.

## Status por area

| Area | Status observado | Comentario |
|---|---|---|
| Backend API | Implementado, pendiente de estabilizacion | Existen controladores, rutas, modelos, requests y resources para los procesos principales. |
| Frontend web | No implementado | `routes/web.php` solo devuelve `resources/views/welcome.blade.php`, la pagina inicial de Laravel. |
| Base de datos | Modelada | Hay 16 migraciones y relaciones para el dominio; no se verifico una BD en ejecucion. |
| Login/autenticacion | Implementado con riesgos | Usa tokens Bearer de Sanctum; falta caducidad y revocacion al desactivar empleados. |
| Roles/permisos | Implementado parcialmente | Middleware por nombre de rol; requiere endurecimiento y pruebas adicionales. |
| Pruebas | Escritas, no ejecutables actualmente | 52 pruebas feature quedan bloqueadas por MySQL no disponible; SQLite tampoco esta habilitado. |
| Documentacion | Parcial/desactualizada | `README.md` afirma Laravel 11, pero el lockfile instala Laravel 10.50.0. |
| Preparacion productiva | No lista | Se requieren correcciones de seguridad, integridad, CI/test DB, documentacion y frontend si forma parte del alcance. |

## Hallazgos prioritarios

| Prioridad | Hallazgo | Evidencia principal |
|---|---|---|
| Critica | Un empleado desactivado puede seguir usando tokens ya emitidos; `CheckRol` no verifica `estado` y no se revocan tokens en `destroy`. | `app/Http/Controllers/EmpleadoController.php:82`, `app/Http/Middleware/CheckRol.php:17`, `app/Http/Controllers/AuthController.php:28` |
| Alta | Los tokens Sanctum no tienen expiracion configurada. | `config/sanctum.php:49` |
| Alta | Los recursos `tipos-empleado` y `clases-servicio` registran parametros de ruta distintos a los nombres tipados de los controladores; sus acciones por ID deben corregirse/verificarse. | `routes/api.php:25`, `routes/api.php:26`, `app/Http/Controllers/TipoEmpleadoController.php:24`, `app/Http/Controllers/ClaseServicioController.php:24` |
| Alta | La integridad financiera permite edicion de totales/corte y no valida consistencia entre `total`, `efectivo`, `tarjeta` y `pagado`. | `app/Http/Requests/StorePagoRequest.php:14`, `app/Http/Requests/UpdatePagoRequest.php:14`, `app/Http/Requests/UpdateCorteRequest.php:14` |
| Alta | La suite funcional no es reproducible en el entorno actual: utiliza MySQL local y no existe servicio disponible; PDO SQLite tampoco esta instalado. | `phpunit.xml:20`, ejecucion `php artisan test` del 2026-05-26 |
| Media | La colision de citas se comprueba solo al crear, no al editar ni con restriccion atomica de base de datos. | `app/Http/Requests/StoreCitaRequest.php:27`, `app/Http/Requests/UpdateCitaRequest.php:14` |
| Media | Crear empleado crea primero la persona sin transaccion; si el segundo insert falla puede quedar una persona huerfana. | `app/Http/Controllers/EmpleadoController.php:22` |
| Media | La lectura de pacientes y empleados esta disponible para cualquier rol autenticado y expone datos personales; debe confirmarse con el negocio. | `routes/api.php:24`, `app/Http/Resources/EmpleadoResource.php:12`, `app/Http/Resources/PersonaResource.php:12` |

## Reportes incluidos

- [01_estado_general.md](01_estado_general.md): explicacion para responsables del proyecto y status de entrega.
- [02_arquitectura_modulos_api.md](02_arquitectura_modulos_api.md): stack, estructura, dominio, autenticacion, permisos y endpoints.
- [03_calidad_seguridad_y_plan.md](03_calidad_seguridad_y_plan.md): hallazgos priorizados, practicas del equipo, pruebas y trabajo pendiente.
- [../../specs/dentista_web_pg_reverse_spec.md](../../specs/dentista_web_pg_reverse_spec.md): especificacion tecnica reconstruida desde el codigo.

## Metodologia y limites

Se revisaron los archivos propios bajo `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `resources/`, `tests/` y los manifiestos/documentos de raiz. Se excluyo la lectura linea por linea de `vendor/` por ser dependencia instalada y de `storage/` por ser salida/runtime. Se consulto `composer.lock` para obtener versiones reales.

Verificaciones ejecutadas:

| Verificacion | Resultado |
|---|---|
| `artisan about` usando PHP de Laragon | Laravel 10.50.0, PHP 8.1.10, entorno local, debug habilitado. |
| `artisan route:list --path=api` | 51 rutas API registradas. |
| `php -l` sobre archivos PHP propios | 128 archivos con sintaxis valida. |
| `artisan test` con MySQL configurado | 2 pruebas triviales pasan; 52 pruebas feature fallan por conexion MySQL rechazada. |
| `artisan test` intentando SQLite en memoria | Bloqueado porque el driver PDO SQLite no esta instalado. |

Existen eliminaciones ya presentes en el arbol de trabajo para `.claude/` y `CLAUDE.md`; no fueron alteradas durante esta auditoria.
