# Documentación de Implementación del Backend

> Fecha: 2026-04-28
> Estado: Fases 1–5 parcialmente completas. Fase 6 (calidad) pendiente.

Este documento explica qué se construyó, en qué archivos, cómo funciona cada pieza y por qué se tomaron las decisiones que se tomaron.

---

## Índice

1. [Cambios en archivos existentes](#1-cambios-en-archivos-existentes)
2. [Modelos de dominio](#2-modelos-de-dominio)
3. [Sistema de autenticación](#3-sistema-de-autenticación)
4. [Middleware de roles](#4-middleware-de-roles)
5. [Controllers](#5-controllers)
6. [Form Requests (validación)](#6-form-requests-validación)
7. [API Resources (serialización)](#7-api-resources-serialización)
8. [Rutas](#8-rutas)
9. [Decisiones que difieren de las convenciones documentadas](#9-decisiones-que-difieren-de-las-convenciones-documentadas)
10. [Pendientes por fase](#10-pendientes-por-fase)

---

## 1. Cambios en archivos existentes

### `config/auth.php`

**Qué cambió:** El proveedor de usuarios `users` apunta a `App\Models\Empleado::class` en lugar del `User` por defecto de Laravel.

**Por qué:** Sanctum necesita saber qué modelo representa al usuario autenticado. Como el sistema usa `Empleado` para el login (no un modelo `User`), hay que indicárselo aquí. Sin este cambio, `auth:sanctum` no podría resolver el usuario desde el token.

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Empleado::class,
    ],
],
```

---

### `config/cors.php`

**Qué cambió:** `allowed_origins` ahora lee la variable de entorno `FRONTEND_URL` (default `http://localhost:5173`) y `supports_credentials` está en `true`.

**Por qué:** El frontend Vue corre en `localhost:5173` (Vite). Sin esta configuración, el navegador bloquea todas las respuestas de la API por política de mismo origen. `supports_credentials: true` es necesario para que Sanctum pueda enviar cookies de sesión si en el futuro se migra a autenticación stateful.

---

### `app/Http/Kernel.php`

**Qué cambió:** Se añadió el alias `'rol'` en `$middlewareAliases`:

```php
'rol' => \App\Http\Middleware\CheckRol::class,
```

**Por qué:** Para poder usar `middleware('rol:admin')` en las rutas en lugar de la clase completa. Es el mecanismo estándar de Laravel para registrar middleware con alias.

---

## 2. Modelos de dominio

Todos los modelos están en `app/Models/`. Todos comparten el mismo patrón base:

```php
protected $primaryKey = 'idXxx'; // PK no estándar — obligatorio
public $incrementing = true;
protected $keyType = 'int';
protected $fillable = [...];
protected $casts = [...];
public function scopeActivos($query) { return $query->where('estado', 1); }
```

**Por qué `$primaryKey` explícito:** Las tablas usan nombres camelCase (`idPersona`, `idEmpleado`, etc.) en vez del `id` que espera Eloquent por defecto. Sin declararlo, cualquier `find()`, `route model binding` o relación fallará silenciosamente al buscar la columna `id` inexistente.

**Por qué `scopeActivos()`:** El patrón de eliminación es lógico (`estado = 0/1`), no `SoftDeletes`. El scope permite escribir `Persona::activos()->get()` en lugar de repetir `->where('estado', 1)` en cada query.

---

### `Persona` (`personas`)

Campos: `idPersona`, `nombre`, `apellidoP`, `apellidoM`, `celular`, `correoElectronico`, `fechaRegistro`, `estado`.

Relaciones:
- `hasMany(Cita)` — un paciente puede tener múltiples citas
- `hasMany(Pago)` — un paciente puede tener múltiples pagos
- `hasOne(Empleado)` — una persona puede ser también empleado (relación inversa)

Cast notable: `fechaRegistro` → `'date'` para que Eloquent lo devuelva como objeto Carbon.

---

### `Empleado` (`empleados`)

Hereda de `Authenticatable` (no de `Model`) porque Sanctum necesita el contrato de autenticación. Usa el trait `HasApiTokens`.

Campos extra sobre Persona: `idPersona` (FK), `idTipoEmpleado` (FK), `usuario`, `rfc`, `contraseña`, `palabraClave`, `cambioContraseña`, `estado`.

Métodos especiales:
- `getAuthPassword()` → retorna `$this->contraseña`. Laravel espera el campo `password` por convención; este método sobreescribe el comportamiento para usar el nombre de columna real.
- `getRememberTokenName()` → retorna `null`. La tabla `empleados` no tiene columna `remember_token`, y sin este override Laravel lanzaría un error al intentar actualizarla.
- `$hidden` incluye `contraseña`, `palabraClave`, `remember_token` para que nunca aparezcan en JSON.

Relaciones:
- `belongsTo(Persona)` — los datos personales están en la tabla `personas`
- `belongsTo(TipoEmpleado)` — define el rol del empleado
- `hasMany(Pago)` — pagos que el empleado registró

---

### `TipoEmpleado` (`tipo_empleados`)

Catálogo simple. Campos: `idTipoEmpleado`, `nombre`, `estado`.

Relación: `hasMany(Empleado)`.

Los valores esperados en `nombre` son: `Administrador`, `Dentista`, `Recepcionista` (con mayúscula inicial). El middleware los normaliza a minúsculas al comparar.

---

### `ClaseServicio` (`clase_servicios`)

Catálogo de categorías de servicios. Campos: `idClaseServicio`, `nombre`, `estado`.

Relación: `hasMany(Servicio)`.

---

### `Servicio` (`servicios`)

Campos: `idServicio`, `idClaseServicio`, `nombre`, `costo`, `duracion`, `estado`.

Casts: `costo` → `'decimal:2'`, `duracion` → no se castea (viene como string `HH:MM:SS` desde MySQL TIME).

Relaciones:
- `belongsTo(ClaseServicio)`
- `hasMany(Cita)`

---

### `Cita` (`citas`)

Campos: `idCita`, `idPersona`, `idServicio`, `fechaRegistro`, `fechaProgramada`, `hora`, `duracion`, `motivo`, `estado`.

Casts: `fechaRegistro` y `fechaProgramada` → `'date'`.

Relaciones:
- `belongsTo(Persona)`
- `belongsTo(Servicio)`
- `hasOne(Receta)` — una cita puede tener a lo mucho una receta

---

### `Receta` (`recetas`)

Campos: `idReceta`, `idCita`, `indicaciones`, `estado`.

Relación: `belongsTo(Cita)`.

La restricción de una-receta-por-cita se valida a nivel de `StoreRecetaRequest` con la regla `unique:recetas,idCita`, no con una restricción de BD (aunque podría agregarse en migración futura).

---

### `Corte` (`cortes`)

Campos: `idCorte`, `fechaInicio`, `fechaFin`, `fDeCaja`, `tEfectivo`, `tTarjeta`, `correcto`, `estado`.

Semántica: un corte "abierto" tiene `fechaFin = NULL`. Al cerrarlo se asigna `fechaFin` y se calculan `tEfectivo`/`tTarjeta` (pendiente de implementar).

Relación: `hasMany(Pago)`.

---

### `Pago` (`pagos`)

Campos: `idPago`, `idPersona`, `idEmpleado`, `idCorte`, `total`, `efectivo`, `tarjeta`, `pagado`, `fechaRegistro`, `estado`.

La columna `pagado` es booleana y se diferencia de `estado`: `estado=0` significa que el pago fue desactivado/anulado, `pagado=true/false` refleja si el monto fue liquidado.

Relaciones:
- `belongsTo(Persona)`
- `belongsTo(Empleado)`
- `belongsTo(Corte)`

---

## 3. Sistema de autenticación

**Archivo:** `app/Http/Controllers/AuthController.php`

Usa Laravel Sanctum con tokens de API (no cookies de sesión).

### `POST /api/login`

1. Valida que vengan `usuario` (string) y `contraseña` (string).
2. Busca el empleado por `usuario` con `estado = 1` (solo activos pueden entrar).
3. Compara la contraseña con `Hash::check()`.
4. Si todo es correcto, genera un token con `createToken('api-token')`.
5. Retorna el token plano, un flag `requiresPasswordChange` y el recurso `EmpleadoResource`.

**Por qué `requiresPasswordChange`:** Cuando un admin crea un empleado, le pone una contraseña inicial y `cambioContraseña = false`. Si el admin activa `cambioContraseña = true`, el frontend debe redirigir al flujo de cambio de contraseña en lugar de dejar entrar al usuario.

### `POST /api/logout`

Elimina únicamente el token actual (`currentAccessToken()->delete()`), no todos los tokens del empleado. Esto permite sesiones múltiples en distintos dispositivos.

### `GET /api/me`

Retorna `EmpleadoResource` del usuario autenticado, con `persona` y `tipoEmpleado` cargados. Sirve para que el frontend hidrate el estado global de usuario al recargar la app.

### `POST /api/change-password`

Requiere `contraseñaActual`, `nuevaContraseña` y `nuevaContraseña_confirmation` (Laravel valida la confirmación con `confirmed`). Al completar el cambio pone `cambioContraseña = false` automáticamente.

---

## 4. Middleware de roles

**Archivo:** `app/Http/Middleware/CheckRol.php`

```php
Route::middleware('rol:admin,recepcionista')->group(...)
```

El middleware recibe los roles permitidos como parámetros variadics (`string ...$roles`).

**Flujo:**
1. Obtiene el empleado autenticado del request.
2. Llama `loadMissing('tipoEmpleado')` — si la relación ya venía cargada (eager load previo) no genera query extra.
3. Lee `$empleado->tipoEmpleado->nombre` (p. ej. `"Administrador"`).
4. Normaliza a alias corto con el mapa: `Administrador → admin`, `Dentista → dentista`, `Recepcionista → recepcionista`.
5. Verifica si el alias está en el array de roles permitidos.
6. Retorna 403 si no tiene permiso.

**Por qué mapear a alias:** Los valores en BD tienen mayúscula inicial y podrían tener variaciones. El mapa centraliza la normalización en un solo lugar en vez de hacer `strtolower()` en cada comparación con los strings de las rutas.

**Caso borde:** Si `tipoEmpleado` es `null` (empleado sin tipo asignado), `$rolActual` queda en `null` y el middleware retorna 403. Un empleado mal configurado no puede operar.

---

## 5. Controllers

Todos los controllers están en `app/Http/Controllers/` (raíz, no en subdirectorio `Api/`).

El patrón CRUD es uniforme en todos:

| Método | Acción | Comportamiento |
|---|---|---|
| `index()` | Listar | `Modelo::activos()->get()` — solo registros con estado=1 |
| `store()` | Crear | `Modelo::create($request->validated() + ['estado' => true])` |
| `show()` | Ver uno | Model binding + `load()` de relaciones relevantes |
| `update()` | Editar | `$modelo->update($request->validated())` |
| `destroy()` | Desactivar | `$modelo->update(['estado' => false])` → 204 |

**Por qué `destroy()` es eliminación lógica:** El contrato del sistema usa `estado = 0/1` como soft-enable. Un DELETE físico de BD borraría historial médico o financiero, lo cual no es aceptable en un consultorio.

---

### `AuthController`

Ver sección 3.

---

### `PersonaController`

- `index()` retorna solo activos.
- `show()` carga `citas` y `pagos` (detalle completo del paciente).
- `store()` inyecta `estado: true` automáticamente.

**Pendiente:** Búsqueda por `?search=` (nombre/apellido).

---

### `EmpleadoController`

`store()` es en dos pasos porque la BD separa los datos personales en `personas` y los datos de acceso en `empleados`:

1. Crea registro en `personas` con los campos personales.
2. Usa el `idPersona` generado para crear el registro en `empleados`.
3. Hashea `contraseña` y `palabraClave` con `bcrypt` antes de guardar.
4. Fuerza `cambioContraseña = false` al crear (el admin lo activa manualmente si quiere que el empleado cambie su contraseña en el primer login).

`update()` re-hashea `contraseña` y `palabraClave` si vienen en el request (son opcionales en update).

**Pendiente:** Endpoint `POST /api/empleados/{id}/reset-password`.

---

### `CitaController`

- `index()` carga `persona` y `servicio` en el listado (evita N+1).
- `show()` carga además `receta`.
- `store()` inyecta `fechaRegistro: now()` y `estado: true`.

**Pendiente:** Filtros `?fecha=`, `?paciente_id=`, `?servicio_id=`. Validación de colisión de horario.

---

### `RecetaController`

Sigue el patrón estándar. La restricción de única receta por cita está en `StoreRecetaRequest`, no en el controller.

---

### `ServicioController` / `ClaseServicioController` / `TipoEmpleadoController`

CRUD estándar sin lógica especial.

---

### `PagoController`

- `index()` carga `persona`, `empleado.persona` y `corte` para mostrar datos contextuales.
- `store()` actualmente acepta `idEmpleado` desde el body del request.

**Pendiente:** `idEmpleado` debe tomarse del usuario autenticado (`$request->user()->idEmpleado`), no del body, para evitar que alguien registre un pago a nombre de otro empleado. También falta validar que haya un corte abierto al registrar.

---

### `CorteController`

- `show()` carga `pagos.persona` para el detalle del corte.

**Pendiente:** 
- Impedir abrir un segundo corte si ya hay uno con `fechaFin = NULL`.
- Al hacer `update()` con `fechaFin`, calcular y guardar `tEfectivo` y `tTarjeta` sumando los pagos del período.
- Endpoint `GET /api/cortes/activo` (definido en el contrato de API pero no registrado en rutas todavía).

---

## 6. Form Requests (validación)

Todos están en `app/Http/Requests/`. Todos tienen `authorize(): true` (la autorización se maneja en las rutas con `CheckRol`, no en los Form Requests).

Todos tienen `messages()` en español.

### Patrón Update vs Store

Los `UpdateXxxRequest` son más permisivos que los `StoreXxxRequest`:
- Los campos obligatorios en Store son `sometimes|required` o simplemente `nullable` en Update.
- `usuario` en `UpdateEmpleadoRequest` usa `unique:empleados,usuario,' . $this->route('empleado')?->idEmpleado` para ignorar el propio registro al validar unicidad.

### Validaciones destacadas

**`StoreRecetaRequest`**
```php
'idCita' => 'required|integer|exists:citas,idCita|unique:recetas,idCita',
```
La regla `unique:recetas,idCita` garantiza que una cita no pueda tener dos recetas. Si ya existe una receta para esa cita, retorna 422 con el mensaje `"Esta cita ya tiene una receta registrada."`.

**`StoreCitaRequest`**
```php
'hora' => 'required|date_format:H:i',
'duracion' => 'nullable|date_format:H:i:s',
```
La hora se valida como `HH:MM` y la duración como `HH:MM:SS` para coincidir con el tipo TIME de MySQL.

**`StorePagoRequest`**
```php
'idEmpleado' => 'required|integer|exists:empleados,idEmpleado',
```
Actualmente el empleado se recibe en el body. Esto cambiará cuando se implemente la extracción desde el token autenticado.

---

## 7. API Resources (serialización)

Todos están en `app/Http/Resources/`. Todos usan `whenLoaded()` para relaciones — esto significa que las relaciones solo aparecen en el JSON si se cargaron explícitamente con `load()` o `with()` en el controller. Nunca causan queries automáticas inesperadas.

Todas las PKs se renombran de su nombre interno (`idPersona`, `idCita`, etc.) al nombre `id` en el JSON, para que el frontend use una interfaz uniforme.

### `PersonaResource`

Añade campo calculado `nombreCompleto`:
```php
'nombreCompleto' => trim("{$this->nombre} {$this->apellidoP} {$this->apellidoM}"),
```
`trim()` elimina el espacio extra cuando `apellidoM` es null.

### `EmpleadoResource`

Oculta completamente `contraseña`, `palabraClave` y `cambioContraseña`. Los datos personales del empleado se embeben como sub-objeto `persona` (si está cargado), con un `nombreCompleto` calculado.

### `PagoResource`

Añade campo calculado `pendiente`:
```php
'pendiente' => (float) $this->total - ((float) $this->efectivo + (float) $this->tarjeta),
```
Útil para saber cuánto falta cubrir de un pago parcial.

### `CorteResource`

Añade campo calculado `totalRecaudado`:
```php
'totalRecaudado' => (float) $this->tEfectivo + (float) $this->tTarjeta,
```
Suma de lo cobrado en efectivo y tarjeta en el corte.

---

## 8. Rutas

**Archivo:** `routes/api.php`

Las 48 rutas están organizadas en cuatro capas de middleware anidadas:

```
Público
└── POST /api/login

auth:sanctum (todos los autenticados)
├── POST /api/logout
├── GET  /api/me
├── POST /api/change-password
│
├── Lectura libre (sin rol)
│   └── GET /api/{tipos-empleado,clases-servicio,personas,servicios,empleados,citas}
│       GET /api/{recurso}/{id}
│
├── rol:admin
│   └── POST/PUT/DELETE /api/{tipos-empleado,clases-servicio,empleados}
│       DELETE /api/recetas/{id}
│       PUT    /api/pagos/{id}
│
├── rol:admin,recepcionista
│   └── POST/PUT/DELETE /api/{personas,servicios,citas}
│       GET/POST/DELETE /api/pagos
│       CRUD completo   /api/cortes
│
└── rol:admin,dentista
    └── GET/POST/PUT /api/recetas
```

**Por qué `apiResource()->only([...])`:** Se usan recursos parciales para que las mismas rutas (`/api/personas`) tengan diferentes niveles de acceso según el método HTTP, sin duplicar la URL. Laravel maneja el binding automático para todos los métodos.

**Nota importante:** Las rutas de auth están en `/api/login`, `/api/me`, `/api/logout` y `/api/change-password`. El contrato de API (`api-contracts.md`) las documenta con prefijo `/api/auth/` — esto es una desviación intencional por simplicidad, ya que el prefijo no agrega valor funcional.

---

## 9. Decisiones que difieren de las convenciones documentadas

| Convención en docs | Lo que se implementó | Razón |
|---|---|---|
| Controllers en `app/Http/Controllers/Api/` | Controllers en raíz `app/Http/Controllers/` | Simplicidad; un solo nivel de directorio es suficiente para el tamaño del proyecto |
| Rutas auth con prefijo `/api/auth/` | Rutas en `/api/login`, `/api/me`, etc. | El prefijo no aporta nada para una API que no tiene múltiples proveedores de auth |
| `PATCH /api/pacientes/{id}/desactivar` + `DELETE` para hard-delete | Solo `DELETE` que hace estado=0 | El hard-delete nunca fue un requisito real del consultorio; un solo endpoint es más simple |
| Alias de rol `administrador` | Alias `admin` | Más corto, menos propenso a typos en las rutas |
| Políticas (Policies) de Laravel para autorización compleja | Solo middleware de rol | Las reglas de acceso son por rol, no por ownership de recurso; una Policy sería sobreingeniería en esta etapa |

---

## 10. Pendientes por fase

### Fase 2 — Pacientes y Citas
- `PersonaController::index` — agregar `?search=` para filtrar por nombre/apellido
- `CitaController::index` — agregar `?fecha=`, `?paciente_id=`, `?servicio_id=`
- `StoreCitaRequest` — validar que no haya otra cita en la misma `fechaProgramada + hora + idServicio`

### Fase 4 — Cortes y Pagos
- `CorteController::store` — verificar que no haya corte con `fechaFin IS NULL` antes de crear uno nuevo
- `CorteController::update` — al recibir `fechaFin`, calcular y persistir `tEfectivo` y `tTarjeta` sumando los pagos del período (`WHERE idCorte = ? AND estado = 1`)
- Añadir ruta `GET /api/cortes/activo` y su método en el controller
- `PagoController::store` — reemplazar `idEmpleado` del body por `$request->user()->idEmpleado`; validar que exista un corte abierto

### Fase 5 — Empleados
- `EmpleadoController` — agregar método `resetPassword()` + ruta `POST /api/empleados/{id}/reset-password` (solo admin); genera nueva contraseña y activa `cambioContraseña = true`

### Fase 6 — Calidad
- Feature tests para los 10 módulos con casos de autenticación y rol
- Eager loading donde falte (revisar N+1 en listados)
- Ajustar `FRONTEND_URL` en `.env` de producción
- Considerar rate limiting diferenciado para `/api/login`
