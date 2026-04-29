# Contratos de la API REST

## Convenciones generales

- Prefijo: `/api/`
- Formato: JSON siempre
- Autenticación: `Authorization: Bearer {token}` (Sanctum) en todos los endpoints excepto login
- Paginación: todos los listados paginan por defecto (15 ítems), parámetro `?per_page=N&page=N`
- Filtro de estado: todos los listados retornan solo registros con `estado=1` salvo que se especifique `?incluir_inactivos=true` (solo admin)
- Códigos HTTP estándar: `200`, `201`, `204`, `400`, `401`, `403`, `404`, `422`, `500`

### Estructura de respuesta exitosa

```json
{
  "data": { ... } | [ ... ],
  "message": "Operación exitosa",
  "meta": { "current_page": 1, "total": 50 }  // solo en listados
}
```

### Estructura de respuesta de error

```json
{
  "message": "Descripción del error",
  "errors": { "campo": ["Error de validación"] }  // solo en 422
}
```

---

## Módulo: Auth

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| POST | `/api/auth/login` | Iniciar sesión | Público |
| POST | `/api/auth/logout` | Cerrar sesión | Autenticado |
| GET | `/api/auth/me` | Datos del usuario actual | Autenticado |
| PUT | `/api/auth/password` | Cambiar contraseña | Autenticado |

### POST /api/auth/login
**Request:**
```json
{ "usuario": "jdoe", "contraseña": "secret123" }
```
**Response 200:**
```json
{
  "token": "1|abc123...",
  "requires_password_change": false,
  "empleado": {
    "idEmpleado": 1,
    "nombre": "Juan Doe",
    "rol": "Recepcionista"
  }
}
```

---

## Módulo: Pacientes (Persona)

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| GET | `/api/pacientes` | Listar pacientes | Todos |
| GET | `/api/pacientes/{id}` | Ver paciente | Todos |
| POST | `/api/pacientes` | Crear paciente | Admin, Recepcionista |
| PUT | `/api/pacientes/{id}` | Editar paciente | Admin, Recepcionista |
| PATCH | `/api/pacientes/{id}/desactivar` | Eliminación lógica (estado=0) | Admin, Recepcionista |
| DELETE | `/api/pacientes/{id}` | Eliminar permanente de BD | Solo Admin |

### GET /api/pacientes — Query params
`?search=nombre_o_apellido` — búsqueda por texto

### POST /api/pacientes — Payload
```json
{
  "nombre": "María",
  "apellidoP": "García",
  "apellidoM": "López",
  "celular": "6121234567",
  "correoElectronico": "maria@ejemplo.com"
}
```

---

## Módulo: Citas

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| GET | `/api/citas` | Listar citas | Todos |
| GET | `/api/citas/{id}` | Ver cita | Todos |
| POST | `/api/citas` | Crear cita | Admin, Recepcionista |
| PUT | `/api/citas/{id}` | Editar cita | Admin, Recepcionista |
| PATCH | `/api/citas/{id}/desactivar` | Cancelar cita (estado=0) | Admin, Recepcionista |
| DELETE | `/api/citas/{id}` | Eliminar permanente | Solo Admin |

### GET /api/citas — Query params
- `?fecha=YYYY-MM-DD` — filtrar por día
- `?paciente_id=N` — filtrar por paciente
- `?servicio_id=N` — filtrar por servicio

### POST /api/citas — Payload
```json
{
  "idPersona": 5,
  "idServicio": 2,
  "fechaProgramada": "2024-08-15",
  "hora": "10:30",
  "motivo": "Revisión de bracket"
}
```

---

## Módulo: Recetas

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| GET | `/api/recetas/{id}` | Ver receta | Admin, Dentista |
| GET | `/api/citas/{citaId}/receta` | Ver receta de una cita | Admin, Dentista |
| POST | `/api/citas/{citaId}/receta` | Crear receta para cita | Admin, Dentista |
| PUT | `/api/recetas/{id}` | Editar receta | Admin, Dentista |
| DELETE | `/api/recetas/{id}` | Desactivar receta | Admin |

### POST /api/citas/{citaId}/receta — Payload
```json
{ "indicaciones": "Tomar ibuprofeno 400mg cada 8 horas por 3 días" }
```

---

## Módulo: Servicios

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| GET | `/api/servicios` | Listar servicios | Todos |
| GET | `/api/servicios/{id}` | Ver servicio | Todos |
| POST | `/api/servicios` | Crear servicio | Admin, Recepcionista |
| PUT | `/api/servicios/{id}` | Editar servicio | Admin, Recepcionista |
| PATCH | `/api/servicios/{id}/desactivar` | Desactivar (estado=0) | Admin, Recepcionista |
| DELETE | `/api/servicios/{id}` | Eliminar permanente | Solo Admin |
| GET | `/api/clases-servicio` | Listar categorías | Todos |
| POST | `/api/clases-servicio` | Crear categoría | Admin |

### POST /api/servicios — Payload
```json
{
  "idClaseServicio": 1,
  "nombre": "Limpieza dental",
  "costo": 450.00,
  "duracion": "00:45:00"
}
```

---

## Módulo: Empleados

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| GET | `/api/empleados` | Listar empleados | Admin, Dentista, Recepcionista |
| GET | `/api/empleados/{id}` | Ver empleado | Admin, Dentista, Recepcionista |
| POST | `/api/empleados` | Crear empleado | Solo Admin |
| PUT | `/api/empleados/{id}` | Editar empleado | Solo Admin |
| PATCH | `/api/empleados/{id}/desactivar` | Desactivar (estado=0) | Solo Admin |
| DELETE | `/api/empleados/{id}` | Eliminar permanente | Solo Admin |

### POST /api/empleados — Payload
```json
{
  "nombre": "Luis",
  "apellidoP": "Martínez",
  "apellidoM": "Soto",
  "celular": "6127654321",
  "correoElectronico": "luis@clinica.com",
  "idTipoEmpleado": 2,
  "usuario": "lmartinez",
  "rfc": "MASL800101ABC",
  "contraseña": "password_inicial"
}
```

---

## Módulo: Pagos

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| GET | `/api/pagos` | Listar pagos | Admin, Recepcionista |
| GET | `/api/pagos/{id}` | Ver pago | Admin, Recepcionista |
| POST | `/api/pagos` | Registrar pago | Admin, Recepcionista |
| PUT | `/api/pagos/{id}` | Editar pago | Admin |

### POST /api/pagos — Payload
```json
{
  "idPersona": 5,
  "idCorte": 3,
  "efectivo": 200.00,
  "tarjeta": 250.00
}
```
> `idEmpleado` se toma del usuario autenticado automáticamente en el controller.

---

## Módulo: Cortes de Caja

| Método | Ruta | Descripción | Roles |
|---|---|---|---|
| GET | `/api/cortes` | Listar cortes | Admin, Recepcionista |
| GET | `/api/cortes/activo` | Corte actualmente abierto | Admin, Recepcionista |
| GET | `/api/cortes/{id}` | Ver detalle de corte | Admin, Recepcionista |
| POST | `/api/cortes` | Abrir nuevo corte | Admin, Recepcionista |
| PUT | `/api/cortes/{id}/cerrar` | Cerrar corte activo | Admin, Recepcionista |

### POST /api/cortes — Payload
```json
{ "fDeCaja": 500.00 }
```
> `fechaInicio` se asigna automáticamente con `now()`.

### PUT /api/cortes/{id}/cerrar — Payload
```json
{}
```
> `fechaFin`, `tEfectivo` y `tTarjeta` se calculan automáticamente sumando los pagos del período.
