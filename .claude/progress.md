# Estado de Implementación — Recap

> Última actualización: 2026-04-28
> Referencia completa de lo que fue construido en la sesión de desarrollo inicial.

---

## Resumen de fases

| Fase | Estado |
|---|---|
| 1.1 Modelos | ✅ Completo |
| 1.2 Autenticación | ✅ Completo |
| 1.3 Middleware de roles | ✅ Completo |
| 2 Pacientes y Citas | 🔶 Parcial — CRUD listo, faltan filtros y validación de colisión |
| 3 Recetas | 🔶 Parcial — CRUD listo, falta restricción de acceso completa |
| 4 Pagos y Cortes | 🔶 Parcial — CRUD listo, faltan validaciones de negocio |
| 5 Empleados | 🔶 Parcial — CRUD listo, falta endpoint reset-password |
| 6 Calidad | ❌ Sin tests ni documentación |

---

## Archivos creados durante la implementación

### Controllers (`app/Http/Controllers/`)
> **Nota:** Están en la raíz de Controllers, NO en `Api/` como sugiere `dev-conventions.md`.

- `AuthController.php` — login, logout, me, changePassword
- `PersonaController.php` — CRUD
- `EmpleadoController.php` — CRUD con creación en dos pasos (Persona + Empleado)
- `CitaController.php` — CRUD
- `RecetaController.php` — CRUD
- `ServicioController.php` — CRUD
- `PagoController.php` — CRUD
- `CorteController.php` — CRUD
- `TipoEmpleadoController.php` — CRUD
- `ClaseServicioController.php` — CRUD

### Form Requests (`app/Http/Requests/`)
18 clases (Store + Update por módulo). Todos tienen `authorize(): true` y `messages()` en español.

- `Store/UpdatePersonaRequest`
- `Store/UpdateEmpleadoRequest` — Update usa `unique` con exclusión del registro actual via `$this->route('empleado')?->idEmpleado`
- `Store/UpdateCitaRequest`
- `Store/UpdateRecetaRequest`
- `Store/UpdateServicioRequest`
- `Store/UpdatePagoRequest`
- `Store/UpdateCorteRequest`
- `Store/UpdateTipoEmpleadoRequest`
- `Store/UpdateClaseServicioRequest`

### API Resources (`app/Http/Resources/`)
9 clases. Todas usan `whenLoaded()` para relaciones y renombran PKs a `id` en camelCase.

- `PersonaResource` — añade campo calculado `nombreCompleto`
- `EmpleadoResource` — oculta contraseña, palabraClave, cambioContraseña; inlinea Persona como sub-objeto
- `TipoEmpleadoResource`
- `ClaseServicioResource`
- `ServicioResource`
- `CitaResource`
- `RecetaResource`
- `PagoResource` — añade campo calculado `pendiente` (total - efectivo - tarjeta)
- `CorteResource` — añade campo calculado `totalRecaudado` (tEfectivo + tTarjeta)

### Middleware
- `app/Http/Middleware/CheckRol.php` — lee TipoEmpleado.nombre, mapea a alias corto, retorna 403

---

## Decisiones de implementación que difieren de `dev-conventions.md`

1. **Eliminación lógica únicamente:** `destroy()` hace `estado=0`, no DELETE físico. El patrón documentado de `PATCH /desactivar` + `DELETE` no fue implementado — se usó solo `DELETE` como lógico para simplicidad. Si en el futuro se necesita hard delete, habrá que añadir métodos separados.

2. **Alias de roles en `CheckRol`:** El middleware usa `admin`, `dentista`, `recepcionista` (alias cortos), NO `administrador` como sugiere `dev-conventions.md`. Las rutas usan `rol:admin`, no `rol:administrador`.

3. **Rutas de auth:** Están en `/api/login`, `/api/me`, `/api/logout` — NO en `/api/auth/login` etc. como propone la convención.

4. **Controllers en raíz:** Están en `app/Http/Controllers/`, no en `app/Http/Controllers/Api/`.

---

## Comportamientos clave no obvios

### Login
- Valida `usuario` (string) + `contraseña` (string)
- Solo empleados con `estado=1` pueden hacer login
- Retorna `requiresPasswordChange: true` si `Empleado.cambioContraseña = true`
- Retorna `EmpleadoResource` con persona y tipoEmpleado cargados

### changePassword
- Ruta: `POST /api/change-password` (requiere auth:sanctum)
- Body: `contraseñaActual`, `nuevaContraseña`, `nuevaContraseña_confirmation`
- Al cambiar: pone `cambioContraseña = false`

### EmpleadoController::store
- Crea primero un registro en `personas` con los datos personales
- Luego crea `empleados` con `idPersona` del registro anterior
- Hashea `contraseña` y `palabraClave` con bcrypt
- Fuerza `cambioContraseña = false` al crear

### CheckRol
- `loadMissing('tipoEmpleado')` evita query extra si ya viene cargado
- Si `tipoEmpleado` es null → 403 (empleado sin tipo asignado no puede operar)
- Mapa de normalización: `Administrador → admin`, `Dentista → dentista`, `Recepcionista → recepcionista`

---

## Estructura de rutas actual (`routes/api.php`)

```
POST   /api/login                          → público

[auth:sanctum]
  POST   /api/logout
  GET    /api/me
  POST   /api/change-password

  [lectura libre — todos los roles]
  GET    /api/tipos-empleado
  GET    /api/tipos-empleado/{id}
  GET    /api/clases-servicio
  GET    /api/clases-servicio/{id}
  GET    /api/personas
  GET    /api/personas/{id}
  GET    /api/servicios
  GET    /api/servicios/{id}
  GET    /api/empleados
  GET    /api/empleados/{id}
  GET    /api/citas
  GET    /api/citas/{id}

  [rol:admin]
  POST/PUT/DELETE /api/tipos-empleado
  POST/PUT/DELETE /api/clases-servicio
  POST/PUT/DELETE /api/empleados
  DELETE          /api/recetas/{id}
  PUT/PATCH       /api/pagos/{id}

  [rol:admin,recepcionista]
  POST/PUT/DELETE /api/personas
  POST/PUT/DELETE /api/servicios
  POST/PUT/DELETE /api/citas
  GET/POST/DELETE /api/pagos
  GET/POST/PUT/DELETE /api/cortes

  [rol:admin,dentista]
  GET/POST/PUT    /api/recetas
```

---

## Pendientes por fase

### Fase 2
- [ ] Búsqueda por nombre/apellido en `PersonaController::index`
- [ ] Filtros por fecha/paciente/servicio en `CitaController::index`
- [ ] Validar colisión de horario en `StoreCitaRequest` (misma fecha+hora+servicio)

### Fase 4
- [ ] Validar que solo haya un corte abierto (`fechaFin IS NULL`) al hacer `store` en cortes
- [ ] Al cerrar corte (update con `fechaFin`): sumar automáticamente pagos del período en `tEfectivo` y `tTarjeta`
- [ ] En `PagoController::store`: extraer `idEmpleado` del usuario autenticado (no del body)
- [ ] Validar que exista corte abierto al registrar pago

### Fase 5
- [ ] Endpoint `POST /api/empleados/{id}/reset-password` (solo admin)

### Fase 6
- [ ] Feature tests para todos los módulos
- [ ] Documentación de API
- [ ] Optimizar N+1 con eager loading donde falte
- [ ] Revisar CORS para producción
