# Especificacion reconstruida: Sistema de Gestion Dental

## Proposito y alcance observado

El sistema implementa una API REST para administrar operacion basica de un consultorio dental: autenticacion de empleados, pacientes, personal, catalogos, agenda, recetas y caja. Esta especificacion describe exclusivamente lo observado en el codigo disponible al 2026-05-26; no supone funcionalidades no implementadas.

## Arquitectura

| Capa | Implementacion |
|---|---|
| Entrada HTTP | Laravel routes (`routes/api.php`, `routes/web.php`) |
| Autenticacion | Sanctum con token Bearer emitido para `Empleado` |
| Autorizacion | Middleware `CheckRol` segun nombre de `TipoEmpleado` |
| Validacion | `app/Http/Requests/*` |
| Orquestacion | `app/Http/Controllers/*` |
| Persistencia | Modelos Eloquent y migraciones MySQL |
| Representacion | `app/Http/Resources/*`, respuestas JSON |
| Pruebas | PHPUnit feature tests con `RefreshDatabase` |

### Flujo tecnico observado

```text
POST /api/login -> validar credenciales de Empleado activo -> emitir token Sanctum
Bearer token -> auth:sanctum -> rol (cuando aplica) -> FormRequest -> Controller
Controller -> Eloquent/MySQL -> JsonResource -> JSON
```

## Entidades y relaciones

| Entidad | Relacion observada |
|---|---|
| Persona | Tiene muchas citas y pagos; puede tener un empleado |
| Empleado | Pertenece a persona y tipo de empleado; tiene pagos; autentica usuarios |
| TipoEmpleado | Tiene muchos empleados |
| ClaseServicio | Tiene muchos servicios |
| Servicio | Pertenece a clase; tiene muchas citas |
| Cita | Pertenece a persona y servicio; tiene una receta |
| Receta | Pertenece a cita |
| Corte | Tiene muchos pagos |
| Pago | Pertenece a persona, empleado y corte |

## Requisitos funcionales observados (EARS)

### Autenticacion

**OBS-AUT-001:** Cuando se reciba un login con credenciales validas de un empleado activo, el sistema debera emitir un token Sanctum y devolver los datos publicos del empleado.

**OBS-AUT-002:** Cuando se reciba un login para un empleado inactivo o con contrasena incorrecta, el sistema debera responder no autorizado.

**OBS-AUT-003:** Mientras una solicitud utilice autenticacion Sanctum valida, el sistema debera permitir consultar el perfil y cambiar la contrasena del empleado autenticado.

**OBS-AUT-004:** Cuando el empleado cierre su sesion, el sistema debera eliminar el token usado en esa solicitud.

### Autorizacion

**OBS-ROL-001:** Mientras el usuario tenga rol administrador, el sistema debera permitir administrar empleados y catalogos, restablecer contrasenas, eliminar recetas y editar pagos.

**OBS-ROL-002:** Mientras el usuario tenga rol administrador o recepcionista, el sistema debera permitir operar personas, servicios, citas, pagos y cortes.

**OBS-ROL-003:** Mientras el usuario tenga rol administrador o dentista, el sistema debera permitir consultar, crear y actualizar recetas.

### Personas y empleados

**OBS-PER-001:** Cuando se solicite el listado de personas, el sistema debera devolver solo registros activos y permitir busqueda por nombre/apellidos.

**OBS-EMP-001:** Cuando un administrador cree un empleado, el sistema debera crear una persona relacionada, almacenar credenciales hasheadas y asociar un tipo de empleado.

**OBS-EMP-002:** Cuando un administrador restablezca una contrasena, el sistema debera marcar que el empleado debe cambiarla al iniciar sesion.

### Agenda y recetas

**OBS-CIT-001:** Cuando se cree una cita, el sistema debera registrar paciente, servicio, fecha, hora, motivo y estado activo.

**OBS-CIT-002:** Cuando exista una cita activa con la misma fecha, hora y servicio, el sistema debera rechazar la creacion de otra cita equivalente.

**OBS-REC-001:** Cuando un administrador o dentista cree una receta, el sistema debera vincular indicaciones a una cita.

**OBS-REC-002:** Cuando ya exista una receta vinculada a la cita, el sistema debera rechazar otra alta segun la validacion implementada.

### Caja

**OBS-COR-001:** Cuando no exista un corte abierto activo, el sistema debera permitir a administrador o recepcionista abrir un corte con fondo inicial.

**OBS-PAG-001:** Mientras exista un corte abierto activo, el sistema debera permitir registrar pagos asociados al empleado autenticado y al corte abierto.

**OBS-COR-002:** Cuando un corte abierto se cierre por primera vez, el sistema debera sumar efectivo y tarjeta de sus pagos activos y guardar dichos totales.

### Baja logica

**OBS-EST-001:** Cuando se elimine un registro de dominio por los endpoints implementados, el sistema debera cambiar su campo `estado` a falso en vez de eliminarlo fisicamente.

## Requisitos no funcionales observados

| Area | Evidencia |
|---|---|
| Formato | La API devuelve recursos JSON; la interfaz web de negocio no esta implementada. |
| Seguridad de contrasena | Hash Laravel para contrasena y palabra clave en operaciones de empleado. |
| Proteccion de rutas | `auth:sanctum` y middleware de roles en `routes/api.php`. |
| Rate limit | 60 solicitudes por minuto por usuario o IP en `RouteServiceProvider.php:27-29`. |
| CORS | Origen configurado con `FRONTEND_URL`; credenciales permitidas. |
| Persistencia | MySQL configurado; identificadores propios camelCase y bajas con `estado`. |
| Errores | Laravel entrega validacion 422; controladores usan 401/403/404/422/204 en casos concretos. |

## Criterios de aceptacion inferidos para estabilizacion

| ID | Criterio |
|---|---|
| AC-SEC-001 | Dado un token activo, cuando el empleado sea desactivado, entonces ninguna ruta protegida debera aceptar ese token. |
| AC-SEC-002 | Dado un token emitido, cuando supere el tiempo de vida aprobado, entonces la API debera rechazarlo. |
| AC-API-001 | Dado un registro de cada catalogo, cuando se consulte/actualice/desactive por ID, entonces la ruta debera resolver el modelo correcto sin error. |
| AC-FIN-001 | Dado un pago, cuando sus montos no cumplan la regla de negocio aprobada, entonces no debera registrarse como liquidado. |
| AC-FIN-002 | Dado un corte cerrado, cuando se intente alterar sus totales sin flujo de ajuste autorizado, entonces el sistema debera rechazar la operacion. |
| AC-CIT-001 | Dada una cita existente, cuando otra cita se edite para colisionar con ella, entonces la operacion debera rechazarse. |
| AC-TST-001 | Dado un checkout limpio, cuando se ejecute la suite en el entorno documentado/CI, entonces las pruebas feature deberan completarse sin dependencia manual de una BD personal. |

## Incertidumbres funcionales

1. No esta definido si la interfaz de usuario vive en otro repositorio o aun no existe.
2. No esta definida la politica real de pagos parciales, reembolsos, anulaciones ni ajustes de caja.
3. No esta definido el recurso cuya disponibilidad bloquea una cita; actualmente se usa el servicio.
4. No esta definido el principio de minimo privilegio para datos personales o clinicos.
5. No esta definido si `palabraClave` sera usada para recuperacion de contrasena u otro proceso.

## Recomendaciones derivadas

1. Resolver primero revocacion/caducidad de tokens, verificacion de empleados activos e integridad financiera.
2. Establecer un entorno de pruebas reproducible y cubrir catalogos, bajas logicas, tokens e invariantes.
3. Publicar contrato OpenAPI y actualizar la documentacion para Laravel 10.50.0.
4. Confirmar el alcance de frontend y las reglas clinicas/contables faltantes antes de ampliar endpoints.
