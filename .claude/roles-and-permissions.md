# Roles y Permisos

## Roles del sistema

El rol de un Empleado se determina por su `TipoEmpleado`. Hay tres roles:

| Rol | TipoEmpleado.nombre | Descripción |
|---|---|---|
| `admin` | Administrador | Control total del sistema |
| `dentista` | Dentista | Acceso clínico (citas, recetas, pacientes) |
| `recepcionista` | Recepcionista | Agenda, pagos, pacientes |

---

## Matriz de permisos por módulo

> **Regla global:**
> - **Eliminación lógica** (estado=0): Admin y Recepcionista en todos los módulos donde aplica.
> - **Eliminación permanente** (DELETE físico de BD): Solo Admin, en cualquier módulo.
> - El Dentista nunca elimina ni desactiva nada (solo lectura + recetas).

---

### Pacientes (Persona)

| Acción | Admin | Dentista | Recepcionista |
|---|---|---|---|
| Ver listado | ✅ | ✅ | ✅ |
| Ver detalle | ✅ | ✅ | ✅ |
| Crear | ✅ | ❌ | ✅ |
| Editar | ✅ | ❌ | ✅ |
| Desactivar (estado=0) | ✅ | ❌ | ✅ |
| Eliminar permanente | ✅ | ❌ | ❌ |

---

### Citas

| Acción | Admin | Dentista | Recepcionista |
|---|---|---|---|
| Ver listado | ✅ | ✅ | ✅ |
| Ver detalle | ✅ | ✅ | ✅ |
| Crear | ✅ | ❌ | ✅ |
| Editar | ✅ | ❌ | ✅ |
| Desactivar (estado=0) | ✅ | ❌ | ✅ |
| Eliminar permanente | ✅ | ❌ | ❌ |

---

### Recetas

| Acción | Admin | Dentista | Recepcionista |
|---|---|---|---|
| Ver | ✅ | ✅ | ❌ |
| Crear | ✅ | ✅ | ❌ |
| Editar | ✅ | ✅ | ❌ |
| Desactivar (estado=0) | ✅ | ❌ | ❌ |
| Eliminar permanente | ✅ | ❌ | ❌ |

---

### Servicios

| Acción | Admin | Dentista | Recepcionista |
|---|---|---|---|
| Ver listado | ✅ | ✅ | ✅ |
| Ver detalle | ✅ | ✅ | ✅ |
| Crear | ✅ | ❌ | ✅ |
| Editar | ✅ | ❌ | ✅ |
| Desactivar (estado=0) | ✅ | ❌ | ✅ |
| Eliminar permanente | ✅ | ❌ | ❌ |

---

### Empleados

| Acción | Admin | Dentista | Recepcionista |
|---|---|---|---|
| Ver listado | ✅ | ✅ | ✅ |
| Ver detalle | ✅ | ✅ | ✅ |
| Crear | ✅ | ❌ | ❌ |
| Editar | ✅ | ❌ | ❌ |
| Desactivar (estado=0) | ✅ | ❌ | ❌ |
| Eliminar permanente | ✅ | ❌ | ❌ |

---

### Pagos

| Acción | Admin | Dentista | Recepcionista |
|---|---|---|---|
| Ver listado / detalle | ✅ | ❌ | ✅ |
| Registrar pago | ✅ | ❌ | ✅ |
| Editar | ✅ | ❌ | ❌ |
| Desactivar (estado=0) | ✅ | ❌ | ✅ |
| Eliminar permanente | ✅ | ❌ | ❌ |

---

### Cortes de caja

| Acción | Admin | Dentista | Recepcionista |
|---|---|---|---|
| Ver historial | ✅ | ❌ | ✅ |
| Ver detalle / totales | ✅ | ❌ | ✅ |
| Abrir corte | ✅ | ❌ | ✅ |
| Cerrar corte | ✅ | ❌ | ✅ |
| Desactivar (estado=0) | ✅ | ❌ | ✅ |
| Eliminar permanente | ✅ | ❌ | ❌ |

---

## Implementación en Laravel

### Middleware de rol

Crear `app/Http/Middleware/CheckRol.php`:

```php
// Ejemplo de uso en rutas:
Route::middleware(['auth:sanctum', 'rol:admin'])->group(function () { ... });
Route::middleware(['auth:sanctum', 'rol:admin,recepcionista'])->group(function () { ... });
```

El middleware obtiene el rol así:
```php
$empleado = $request->user(); // Empleado autenticado
$rol = $empleado->tipoEmpleado->nombre; // 'Administrador', 'Dentista', etc.
```

Normalizar a minúsculas para comparar: `strtolower($rol)` → `administrador`, `dentista`, `recepcionista`.

### Policies de Laravel

Para recursos con lógica de permisos más compleja, usar Policies:
- `PersonaPolicy`
- `CitaPolicy`
- `RecetaPolicy`

Registrar en `AuthServiceProvider`.

---

## Autenticación (Sanctum)

- Login: `POST /api/auth/login` con `usuario` + `contraseña`
- El modelo `Empleado` debe usar el trait `HasApiTokens`
- Devolver el token en la respuesta del login
- El frontend almacena el token y lo envía en cada request: `Authorization: Bearer {token}`
- Logout: `POST /api/auth/logout` — revoca el token actual

### Consideración: `cambioContraseña`

Si `Empleado.cambioContraseña = true`, la API debe retornar un flag especial en el login para que el frontend redirija al flujo de cambio de contraseña. El empleado no puede usar la app hasta completarlo.

```json
{
  "token": "...",
  "requires_password_change": true
}
```
