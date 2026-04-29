# Roadmap de Desarrollo

## Estado actual del proyecto

> Última actualización: 2026-04-28

- ✅ Migraciones creadas (tablas con estructura base)
- ✅ `estado` agregado a `cortes` (migración `2026_04_27_000001_add_estado_to_cortes_table`)
- ✅ `CorteFactory` actualizado para incluir `estado`
- ✅ `domain-model.md` sincronizado: campos de Cita, Pago y Corte verificados contra BD
- ✅ Seeders con datos de prueba en orden de dependencia
- ✅ Modelos completos: `$primaryKey`, `$fillable`, `$casts`, `scopeActivos()` y relaciones Eloquent en los 9 modelos de dominio
- ✅ `Empleado` convertido a `Authenticatable` con `HasApiTokens` y `getAuthPassword()`
- ✅ `config/auth.php` apunta a `Empleado::class` como proveedor de usuarios
- ✅ Controllers CRUD para los 9 módulos + AuthController
- ✅ 48 rutas API registradas (`api.php`)
- ✅ 18 Form Requests con validaciones y mensajes en español
- ✅ 9 API Resources con camelCase, campos sensibles ocultos y campos calculados
- ✅ `CheckRol` middleware con alias `rol:` registrado en Kernel
- ✅ Rutas reorganizadas por grupos de rol (admin / admin+recepcionista / admin+dentista / lectura libre)
- ❌ Sin tests

---

## Fase 1 — Fundación (hacer primero, es bloqueante)

**Objetivo:** El agente puede autenticarse y el sistema de roles funciona.

### 1.1 Completar modelos
- [x] Agregar `$primaryKey` a todos los modelos (ver `dev-conventions.md`)
- [x] Agregar `$fillable` a todos los modelos
- [x] Agregar `$casts` relevantes
- [x] Definir todas las relaciones Eloquent (ver `domain-model.md`)
- [x] Agregar scope `activos()` a todos los modelos
- [x] Configurar `Empleado` como modelo autenticable con Sanctum

### 1.2 Autenticación
- [x] Configurar `config/auth.php` para usar `Empleado`
- [x] Crear `AuthController` con: `login`, `logout`, `me`
- [x] Agregar `changePassword` al AuthController
- [x] Manejar flag `cambioContraseña` en login (retorna `requiresPasswordChange: true`)
- [x] Configurar CORS para el origen Vue (`FRONTEND_URL=http://localhost:5173`)
- [x] `login` y `me` usan `EmpleadoResource` (sin campos sensibles)
- [x] Agregar rutas de auth en `api.php`

### 1.3 Middleware de roles
- [x] Crear `CheckRol` middleware
- [x] Registrar en `app/Http/Kernel.php`
- [x] Aplicar a las rutas según `roles-and-permissions.md`

**Criterio de éxito:** Un empleado puede hacer login, recibe token, y puede hacer GET /api/me con ese token.

---

## Fase 2 — Módulo Pacientes y Citas

**Objetivo:** Flujo principal del consultorio funcional.

### 2.1 Pacientes
- [x] `PersonaController` (index, show, store, update, destroy)
- [x] `StorePersonaRequest`, `UpdatePersonaRequest`
- [x] `PersonaResource` con campos ocultos y `nombreCompleto`
- [x] Búsqueda por nombre/apellido (`?search=`)
- [x] Rutas protegidas con roles correctos

### 2.2 Servicios (necesario antes de Citas)
- [x] `ServicioController` (CRUD completo)
- [x] `ClaseServicioController` (CRUD completo)
- [x] `ServicioResource`, `ClaseServicioResource`
- [x] Solo Admin puede crear/editar/desactivar

### 2.3 Citas
- [x] `CitaController` (index, show, store, update, destroy)
- [x] `StoreCitaRequest`, `UpdateCitaRequest`
- [x] `CitaResource`
- [x] Filtros por fecha, paciente, servicio (`?fecha=`, `?paciente_id=`, `?servicio_id=`)
- [x] Validar que no haya colisión de horario (mismo servicio+fecha+hora)

**Criterio de éxito:** Recepcionista puede crear una cita para un paciente en un servicio existente.

---

## Fase 3 — Módulo Recetas

**Objetivo:** Dentista puede documentar prescripciones.

- [x] `RecetaController` (index, show, store, update, destroy)
- [x] `StoreRecetaRequest`, `UpdateRecetaRequest`
- [x] `RecetaResource`
- [x] Solo una receta por cita (validado con `unique` en Form Request)
- [x] Acceso restringido a Dentista y Admin (rutas protegidas con `rol:admin,dentista` / `rol:admin`)

**Criterio de éxito:** Dentista puede agregar indicaciones a una cita existente.

---

## Fase 4 — Módulo Caja (Pagos y Cortes)

**Objetivo:** Flujo de cobro completo.

### 4.1 Cortes
- [x] `CorteController` (index, show, store, update, destroy)
- [x] `StoreCorteRequest`, `UpdateCorteRequest`
- [x] `CorteResource` con `totalRecaudado` calculado
- [x] Validar que solo puede haber un corte abierto a la vez
- [x] Al cerrar: calcular `tEfectivo` y `tTarjeta` sumando pagos del período
- [x] `GET /api/cortes/activo` — endpoint para consultar el corte abierto actual
- [x] `StoreCorteRequest` simplificado: solo requiere `fDeCaja`; `fechaInicio` se asigna con `now()`

### 4.2 Pagos
- [x] `PagoController` (index, show, store, update, destroy)
- [x] `StorePagoRequest`, `UpdatePagoRequest`
- [x] `PagoResource` con `pendiente` calculado
- [x] `idEmpleado` se extrae del usuario autenticado (token Sanctum)
- [x] `idCorte` se toma del corte abierto automáticamente
- [x] Validar que exista un corte abierto al registrar pago

**Criterio de éxito:** Recepcionista puede abrir un corte, registrar pagos, y cerrar el corte con totales calculados.

---

## Fase 5 — Módulo Empleados

**Objetivo:** Admin puede gestionar el staff.

- [x] `EmpleadoController` (CRUD completo)
- [x] `StoreEmpleadoRequest`, `UpdateEmpleadoRequest`
- [x] `EmpleadoResource` con campos sensibles ocultos
- [x] Al crear: `cambioContraseña = false` por defecto (Admin lo activa manualmente)
- [x] Hashear contraseña y palabraClave con `bcrypt`
- [x] Endpoint `POST /api/empleados/{id}/reset-password` (solo admin)
- [x] Solo Admin tiene acceso

**Criterio de éxito:** Admin puede crear un nuevo empleado que luego puede hacer login.

---

## Fase 6 — Calidad y cierre

- [x] Tests Feature para todos los módulos (7 archivos, ~50 casos)
- [x] Documentación de API (`.claude/frontend-guide.md` + `.claude/postman_collection.json`)
- [x] Revisar y ajustar CORS definitivo para producción (documentado en `fase6-calidad.md`)
- [x] Optimizar queries N+1 con `with()` eager loading (`RecetaController::index` corregido)
- [x] Revisión de seguridad: validaciones, autorización, sanitización (ver `fase6-calidad.md`)

---

## Notas de dependencias entre fases

```
Fase 1 (Auth + Modelos)
    └── Fase 2 (Pacientes + Citas)
            └── Fase 3 (Recetas)
            └── Fase 4 (Pagos + Cortes)
    └── Fase 5 (Empleados)
```

Las fases 3, 4 y 5 pueden desarrollarse en paralelo una vez completada la Fase 2.
