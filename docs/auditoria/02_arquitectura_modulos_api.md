# Arquitectura, modulos y API

## Stack verificado

| Elemento | Version/configuracion observada | Fuente |
|---|---|---|
| Lenguaje | PHP 8.1.10 | `artisan about` |
| Framework | Laravel 10.50.0 | `composer.lock`, `artisan about` |
| Autenticacion API | Laravel Sanctum 3.3.3 | `composer.lock`, `config/sanctum.php` |
| ORM | Eloquent | `app/Models/*` |
| Base configurada | MySQL, base local `dentista_db` | `.env` revisado sin exponer secretos |
| Front tooling | Vite 5 + Axios | `package.json`, `vite.config.js` |
| Test framework | PHPUnit 10 | `composer.json`, `phpunit.xml` |

## Estructura relevante

```text
app/
  Http/Controllers/     11 controladores, incluyendo autenticacion
  Http/Middleware/      middleware Laravel y CheckRol
  Http/Requests/        19 validadores de entrada
  Http/Resources/       9 serializadores JSON
  Models/               9 modelos de dominio y User residual
database/
  migrations/           16 migraciones
  factories/            10 factories
  seeders/              seeder general
routes/
  api.php               superficie del producto
  web.php               bienvenida Laravel
tests/
  Feature/              pruebas de siete areas funcionales
  Unit/                 prueba ejemplo
```

## Flujo de solicitud

```text
Cliente
  -> /api/*
  -> middleware api (throttle 60/min + bindings)
  -> auth:sanctum (excepto login)
  -> CheckRol en operaciones restringidas
  -> FormRequest
  -> Controller / Eloquent
  -> JsonResource
  -> respuesta JSON
```

## Autenticacion y permisos

### Login

Si, el login es con token. `POST /api/login` recibe `usuario` y `contrasena` (en el codigo el campo tiene la letra `ñ`), busca un `Empleado` activo, verifica el hash y genera un token personal:

```text
Authorization: Bearer {token}
```

Evidencia: `app/Http/Controllers/AuthController.php:12-34`, `app/Models/Empleado.php:9-49`, `config/auth.php:62-66`, `routes/api.php:15-17`.

### Roles observados

| Rol interno | Acceso de escritura principal |
|---|---|
| `admin` | Catalogos, empleados, reset de contrasena, baja de recetas, edicion de pagos, y operaciones compartidas |
| `recepcionista` | Personas, servicios, citas, pagos y cortes |
| `dentista` | Recetas |

El middleware obtiene el nombre de `tipoEmpleado` y lo mapea a los tres literales esperados. Los roles dependen del texto almacenado en base de datos, no de un identificador estable de permiso.

### Restricciones de lectura

Cualquier usuario autenticado puede listar/ver tipos de empleado, clases, personas, servicios, empleados y citas. Pagos/cortes se restringen a admin o recepcionista; recetas a admin o dentista.

## Dominio de datos

| Entidad | Relaciones principales | Funcion |
|---|---|---|
| `Persona` | citas, pagos, empleado | Pacientes y datos personales; tambien persona asociada a empleado |
| `TipoEmpleado` | empleados | Rol/catalogo laboral |
| `Empleado` | persona, tipoEmpleado, pagos | Usuario autenticable y operador |
| `ClaseServicio` | servicios | Categoria de servicio |
| `Servicio` | claseServicio, citas | Oferta clinica con costo y duracion |
| `Cita` | persona, servicio, receta | Reserva de atencion |
| `Receta` | cita | Indicaciones clinicas vinculadas a una cita |
| `Corte` | pagos | Periodo de caja y sus totales |
| `Pago` | persona, empleado, corte | Cobro registrado |

Casi todas las entidades usan `estado` como baja logica mediante el scope `activos()`. El modelo `User` y su tabla se conservan del esqueleto Laravel, pero la autenticacion de negocio usa `Empleado`.

## Superficie API por modulo

Todos los paths siguientes llevan prefijo `/api`.

| Modulo | Endpoints | Acceso observado |
|---|---|---|
| Sesion | `POST /login` | Publico |
| Sesion | `POST /logout`, `GET /me`, `POST /change-password` | Autenticado |
| Tipos de empleado | CRUD `/tipos-empleado` | Lectura autenticada; escritura admin |
| Clases de servicio | CRUD `/clases-servicio` | Lectura autenticada; escritura admin |
| Personas | CRUD `/personas` | Lectura autenticada; escritura admin/recepcionista |
| Servicios | CRUD `/servicios` | Lectura autenticada; escritura admin/recepcionista |
| Empleados | CRUD `/empleados`, `POST /empleados/{id}/reset-password` | Lectura autenticada; escritura/reset admin |
| Citas | CRUD `/citas` | Lectura autenticada; escritura admin/recepcionista |
| Recetas | CRUD `/recetas` | Lectura/crear/editar admin/dentista; eliminar admin |
| Pagos | CRUD `/pagos` | Ver/crear/eliminar admin/recepcionista; editar admin |
| Cortes | CRUD `/cortes`, `GET /cortes/activo` | Admin/recepcionista |

## Reglas de negocio observadas

| Regla observada | Evidencia |
|---|---|
| Un empleado inactivo no puede iniciar una sesion nueva. | `AuthController.php:19-26` |
| Un token actual se elimina al cerrar sesion. | `AuthController.php:37-42` |
| Una cita nueva se rechaza si ya existe cita activa con igual fecha, hora y servicio. | `StoreCitaRequest.php:27-41` |
| Una cita admite filtros por fecha, paciente y servicio. | `CitaController.php:13-30` |
| Una receta se intenta limitar a una por cita mediante validacion. | `StoreRecetaRequest.php:14-19` |
| Solo puede existir un corte activo sin fecha fin al abrir uno nuevo. | `CorteController.php:28-46` |
| Registrar un pago requiere corte abierto y asigna el empleado autenticado. | `PagoController.php:20-39` |
| Al cerrar inicialmente un corte se calculan montos efectivo/tarjeta de pagos activos. | `CorteController.php:53-70` |

## Modulos no encontrados

No se encontro implementacion propia de interfaz de operacion, odontograma o expediente clinico detallado, inventario, notificaciones, recordatorios, facturacion, reportes exportables, auditoria de cambios, recuperacion de contrasena del usuario final, CI/CD ni documentacion OpenAPI vigente.
