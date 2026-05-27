# Calidad, seguridad y plan para completar

## Veredicto de revision

La intencion del codigo es proveer una API CRUD dental protegida por tokens y roles. La separacion basica de capas es buena, pero el proyecto requiere cambios antes de aceptar datos reales debido a riesgos de autorizacion, tokens, consistencia financiera y falta de verificacion automatizada ejecutable.

**Veredicto:** requiere cambios antes de entrega operativa.

## Problemas criticos y altos

### SEC-01 - Tokens validos despues de desactivar un empleado (critico)

`EmpleadoController::destroy()` solo cambia `estado` a falso. La validacion de `estado` se hace al iniciar sesion, pero un token ya emitido sigue autenticando al empleado; `CheckRol` carga el rol y autoriza sin exigir que siga activo.

- Impacto: una cuenta dada de baja puede continuar leyendo o modificando informacion, incluida informacion clinica/financiera segun su rol.
- Evidencia: `app/Http/Controllers/EmpleadoController.php:82-86`, `app/Http/Controllers/AuthController.php:19-28`, `app/Http/Middleware/CheckRol.php:17-34`.
- Correccion esperada: revocar todos los tokens al desactivar/restablecer credenciales cuando corresponda y aplicar verificacion global de empleado activo a rutas autenticadas.
- Pruebas faltantes: token creado, empleado desactivado, cualquier ruta protegida debe devolver `401` o `403`.

### SEC-02 - Tokens sin vencimiento (alto)

`config/sanctum.php` define `'expiration' => null`. Un token filtrado permanece valido hasta que se elimine manualmente, y actualmente no se eliminan todos los tokens ante baja del empleado.

- Impacto: aumenta la ventana de abuso de credenciales comprometidas.
- Evidencia: `config/sanctum.php:39-49`.
- Correccion esperada: fijar politica de expiracion, revocacion, dispositivos/sesiones y almacenamiento del token en frontend.

### API-01 - Binding inconsistente en dos catalogos (alto)

Artisan registra `{tipos_empleado}` y `{clases_servicio}`, mientras los controladores esperan `$tipoEmpleado` y `$claseServicio`. El binding implicito de Laravel requiere alinear el parametro de ruta con el argumento del controlador o configurar parametros explicitamente.

- Impacto: las operaciones `show`, `update` y `destroy` de ambos catalogos pueden responder con error en lugar de recuperar el modelo.
- Evidencia: `routes/api.php:25-26`, `routes/api.php:34-35`, `TipoEmpleadoController.php:24-40`, `ClaseServicioController.php:24-40`; `artisan route:list` muestra los placeholders.
- Correccion esperada: configurar `->parameters(...)`, cambiar nombres de argumentos/rutas o declarar binding explicito; agregar pruebas feature CRUD de ambos catalogos.

### FIN-01 - Integridad contable insuficiente (alto)

El alta de pago acepta `total`, `efectivo` y `tarjeta` sin regla que determine si el pago esta completo o parcial, pero el controlador marca `pagado=true` siempre. En edicion, un admin puede cambiar montos y `idCorte`; en cortes, el request admite enviar `tEfectivo` y `tTarjeta` y los totales solo se recalculan en el primer cierre.

- Impacto: totales de caja inconsistentes, pagos reportados como pagados con saldo pendiente, traslado de movimientos entre cortes o alteracion posterior al cierre.
- Evidencia: `StorePagoRequest.php:14-21`, `PagoController.php:20-38`, `UpdatePagoRequest.php:14-22`, `UpdateCorteRequest.php:14-22`, `CorteController.php:53-70`, `PagoResource.php:17-18`.
- Correccion esperada: definir formalmente pagos parciales; derivar `pagado`; bloquear o auditar edicion de cortes cerrados; impedir entrada manual de totales derivados; usar transacciones.

## Problemas medios

| ID | Situacion | Riesgo / accion |
|---|---|---|
| CIT-01 | La colision se revisa en `StoreCitaRequest`, no en `UpdateCitaRequest`, y no existe restriccion atomica. | Una edicion o carrera puede duplicar agenda; compartir validacion y definir restriccion/regla real de disponibilidad. |
| DATA-01 | Los listados usan `activos()`, pero los endpoints `show/update/destroy` resuelven el modelo sin comprobar `estado`. | Registros dados de baja siguen visibles/modificables por URL; aplicar scope/binding o regla consistente. |
| EMP-01 | Creacion de empleado inserta `Persona` y luego `Empleado` sin transaccion. | Un fallo de usuario/RFC/BD puede dejar persona huerfana; envolver en `DB::transaction()`. |
| REC-01 | Una receta por cita se limita solo con `unique` del request; la BD no tiene indice unico. | Solicitudes concurrentes pueden crear duplicados; agregar restriccion unica y decidir tratamiento de recetas desactivadas. |
| PRIV-01 | Cualquier empleado autenticado lee pacientes y empleados con telefono/correo/RFC. | Confirmar minimo privilegio y, si procede, limitar campos o roles. |
| CFG-01 | Desarrollo tiene `APP_DEBUG=true`; documentacion no distingue configuracion productiva. | En produccion debe estar deshabilitado y gestionarse secretos/URLs por entorno. |

## Cobertura de pruebas y verificaciones

### Pruebas existentes

| Suite | Comportamiento cubierto por codigo de prueba |
|---|---|
| `AuthTest` | login, token, logout, perfil, cambio de contrasena, ruta protegida |
| `PersonaTest` | lectura activa, busqueda, alta/baja y permisos basicos |
| `EmpleadoTest` | alta, hash, reset, baja y permisos |
| `CitaTest` | alta, filtros, roles y colision al crear |
| `RecetaTest` | alta, rol, unicidad en request y baja |
| `PagoTest` | alta con corte, asignacion de empleado, saldo y roles |
| `CorteTest` | apertura, activo, cierre y totalizacion |

### Resultado de ejecucion

| Comando | Resultado |
|---|---|
| `php -l` en 128 archivos PHP propios | Exitoso |
| `artisan route:list --path=api` | Exitoso, 51 rutas |
| `artisan test` con MySQL de `.env` | 2 pruebas ejemplo pasan; 52 feature fallan antes de la logica por conexion MySQL rechazada |
| `artisan test` con SQLite `:memory:` | 2 pruebas ejemplo pasan; 52 feature fallan por falta de driver PDO SQLite |

No es correcto afirmar que los modulos pasan sus pruebas hasta preparar un motor de pruebas disponible y repetir la suite.

### Pruebas ausentes o insuficientes

1. CRUD y binding de tipos de empleado, clases de servicio y servicios.
2. Revocacion de token por baja de empleado y expiracion.
3. Acceso por URL a entidades desactivadas.
4. Modificacion de cita que genera colision.
5. Pagos parciales, montos inconsistentes, cierre inmutable y concurrencia de corte.
6. Transaccion de alta de empleado ante fallo del segundo insert.
7. Paginacion/volumen, CORS y respuestas de error contractuales.

## Sintaxis, nomenclatura y practicas observadas

### Practicas acertadas

| Practica | Observacion |
|---|---|
| Separacion Laravel convencional | Controlador, modelo, request y resource por entidad facilita navegar el codigo. |
| Validacion centralizada | Se usan `FormRequest` en recursos de negocio en vez de validar manualmente en cada CRUD. |
| Serializacion de salida | `JsonResource` evita exponer hash de contrasena/palabra clave. |
| Hash de credenciales en operaciones reales | `Hash::make()` se usa al crear/cambiar/restablecer contrasena. |
| Roles agrupados en rutas | La matriz de acceso basica es visible en `routes/api.php`. |
| Factories y pruebas feature | Existe una base razonable para estabilizar el backend una vez disponible la BD de pruebas. |

### Convenciones y deuda tecnica

| Tema | Observacion |
|---|---|
| Idioma | Codigo de dominio y mensajes estan en espanol; clases/framework en ingles. Es entendible para el equipo. |
| Identificadores | Se usa camelCase en columnas (`idPersona`, `fechaProgramada`) en vez de convenciones Laravel snake_case. Funciona al declarar llaves/relaciones, pero aumenta configuracion manual. |
| Caracteres acentuados en nombres | Campos como `contrasena` con `ñ` y `cambioContrasena` con `ñ` son validos en PHP/UTF-8, pero complican integraciones, teclados, contratos JSON y herramientas externas. Conviene migrar el contrato a ASCII (`contrasena`). |
| Logica en controladores | Los flujos financieros y de creacion compuesta estan en controladores, sin servicios/transacciones; todavia manejable, pero ya afecta integridad. |
| Autorizacion | Es middleware basado en texto del rol; no existen policies ni permisos granulares. |
| Tipado | Modelos y controladores omiten tipos de retorno en varios metodos; no bloquea funcionamiento pero reduce ayuda estatica. |
| Restos de plantilla | `User`, tabla `users`, `password_reset_tokens`, welcome y configuraciones sin uso generan confusion sobre el usuario real. |
| Documentacion | Existe resumen, pero hay inconsistencia Laravel 10/11 y falta contrato ejecutable de API. |

## Plan recomendado para llegar a operacion confiable

| Fase | Objetivo | Entregables verificables |
|---|---|---|
| P0 Seguridad | Cerrar accesos indebidamente persistentes | Revocacion de tokens, verificacion de activo, expiracion, pruebas de acceso denegado |
| P0 Pruebas | Obtener evidencia reproducible | BD testing aislada o PDO SQLite habilitado, `.env.testing`, suite feature pasando en CI |
| P0 Integridad | Corregir caja/agenda/catalogos | Binding probado, invariantes de pagos/cortes, transacciones, colisiones tambien en update |
| P1 Contrato | Hacer consumible el backend | OpenAPI/Postman actualizado, respuestas/errores documentados, versionado si aplica |
| P1 Privacidad | Ajustar exposicion de datos | Matriz de permisos aprobada, resources restringidos segun rol, auditoria de cambios |
| P2 Producto | Cubrir alcance faltante | Frontend o integracion definida, reportes clinicos/financieros requeridos, despliegue/backup/monitoreo |

## Preguntas que debe resolver el responsable del producto

1. ¿Este repositorio debe entregar tambien la interfaz visual o solo la API para otro frontend?
2. ¿Un dentista debe poder ver datos de todos los pacientes y empleados, o solo citas/pacientes asignados?
3. ¿Se aceptan pagos parciales? Si si, ¿como se cierra caja y cuando `pagado` pasa a verdadero?
4. ¿Un corte cerrado puede corregirse o debe registrarse un ajuste auditado?
5. ¿La disponibilidad de una cita depende del servicio, del dentista, del consultorio/sillon o de una combinacion?
6. ¿Se necesita expediente clinico, consentimiento, odontograma o proteccion adicional de datos sensibles?
