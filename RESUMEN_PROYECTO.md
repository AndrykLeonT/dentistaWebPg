# Resumen del proyecto — Sistema de Gestión Dental

## ¿Qué es este proyecto?

Una **API REST** construida con **Laravel 10** que sirve de backend para un consultorio dental.
El frontend (web o app) se conecta a esta API para gestionar pacientes, citas, empleados, servicios, pagos y cortes de caja.

La comunicación siempre es en formato **JSON**. No hay vistas de HTML generadas por Laravel; todo eso lo hace el frontend.

---

## Estructura de carpetas relevante

```
app/
├── Http/
│   ├── Controllers/        ← Lógica de cada recurso (qué hace cada endpoint)
│   ├── Middleware/         ← Filtros que se ejecutan antes de llegar al controller
│   ├── Requests/           ← Validaciones de los datos que llegan del frontend
│   └── Resources/          ← Formato exacto del JSON que se devuelve al frontend
├── Models/                 ← Representación de cada tabla de la base de datos

database/
├── migrations/             ← Scripts que crean/modifican las tablas en MySQL
├── factories/              ← Generadores de datos falsos para pruebas
└── seeders/                ← Poblan la BD con datos iniciales de prueba

routes/
└── api.php                 ← AQUÍ se declaran todos los endpoints de la API

config/
└── auth.php                ← Configuración de autenticación (qué modelo es el usuario)
```

---

## Base de datos — tablas principales

| Tabla            | Para qué sirve                                                      |
|------------------|---------------------------------------------------------------------|
| `personas`       | Pacientes: nombre, apellidos, celular, correo                       |
| `empleados`      | Personal del consultorio: usuario, contraseña, tipo de empleado     |
| `tipo_empleados` | Catálogo de roles: Administrador, Dentista, Recepcionista           |
| `clase_servicios`| Catálogo de categorías de servicios (limpieza, ortodoncia, etc.)    |
| `servicios`      | Servicios que ofrece el consultorio: nombre, costo, duración        |
| `citas`          | Citas agendadas: paciente + servicio + fecha + hora + motivo        |
| `recetas`        | Receta médica ligada a una cita: indicaciones                       |
| `cortes`         | Corte de caja: período abierto/cerrado con totales de cobros        |
| `pagos`          | Pagos registrados: efectivo y/o tarjeta, ligados a paciente y corte |

Todas las tablas tienen una columna `estado` (1 = activo, 0 = inactivo) que funciona como "borrado lógico": nunca se elimina un registro real, solo se desactiva.

---

## Cómo funciona la autenticación

Se usa **Laravel Sanctum**, que es un sistema de tokens. El flujo es:

1. El frontend manda usuario + contraseña al endpoint `/api/login`
2. El backend verifica las credenciales y devuelve un **token**
3. El frontend guarda ese token y lo manda en cada petición siguiente como header:
   ```
   Authorization: Bearer {token}
   ```
4. El backend valida el token en cada petición protegida

El modelo que representa al usuario autenticado es **Empleado** (no el `User` por defecto de Laravel).
Esto se configuró en `config/auth.php`.

---

## Middleware creado

### `CheckRol` — `app/Http/Middleware/CheckRol.php`

Verifica que el empleado autenticado tenga el rol requerido para ejecutar cierta acción.

Los roles disponibles son:
- `admin` → mapea al tipo de empleado "Administrador" en la BD
- `dentista` → mapea a "Dentista"
- `recepcionista` → mapea a "Recepcionista"

Se usa en `routes/api.php` con la sintaxis `middleware('rol:admin')` o `middleware('rol:admin,recepcionista')`.

Se registró en `app/Http/Kernel.php` con el alias `rol`.

---

## Controllers — uno por cada recurso

Cada controller vive en `app/Http/Controllers/` y contiene los métodos estándar:
`index` (listar), `store` (crear), `show` (ver uno), `update` (editar), `destroy` (desactivar).

| Controller               | Gestiona       | Detalle relevante                                                                 |
|--------------------------|----------------|-----------------------------------------------------------------------------------|
| `AuthController`         | Sesión         | Login, logout, ver mi perfil (`/me`), cambiar contraseña                          |
| `PersonaController`      | Pacientes      | CRUD de pacientes del consultorio                                                 |
| `EmpleadoController`     | Empleados      | CRUD + ruta especial para resetear contraseña de un empleado (`reset-password`)   |
| `TipoEmpleadoController` | Roles          | Catálogo de tipos de empleado                                                     |
| `ClaseServicioController`| Categorías     | Catálogo de categorías de servicios                                               |
| `ServicioController`     | Servicios      | Servicios ofrecidos: costo y duración                                             |
| `CitaController`         | Citas          | Agenda; permite filtrar por fecha, paciente o servicio en el listado              |
| `RecetaController`       | Recetas        | Recetas médicas ligadas a una cita                                                |
| `CorteController`        | Corte de caja  | Abre/cierra períodos de caja; al cerrar calcula automáticamente totales de pagos  |
| `PagoController`         | Pagos          | Registra cobros; automáticamente los asocia al corte de caja abierto             |

---

## Resources — formato del JSON de respuesta

Viven en `app/Http/Resources/`. Cada uno define exactamente qué campos se devuelven al frontend para no exponer datos sensibles (por ejemplo, la contraseña nunca sale en ningún JSON).

Hay un Resource por cada modelo: `PersonaResource`, `EmpleadoResource`, `CitaResource`, etc.

---

## Dónde se declaran los endpoints — `routes/api.php`

**Este es el archivo central.** Toda ruta que el frontend puede llamar está aquí.

### Cómo está organizado:

```
POST   /api/login              ← Pública (sin token)

── Con token (auth:sanctum) ──────────────────────────────
POST   /api/logout
GET    /api/me
POST   /api/change-password

── Lectura libre (cualquier empleado autenticado) ─────────
GET    /api/tipos-empleado
GET    /api/clases-servicio
GET    /api/personas
GET    /api/servicios
GET    /api/empleados
GET    /api/citas

── Solo Admin ─────────────────────────────────────────────
POST/PUT/DELETE  /api/tipos-empleado
POST/PUT/DELETE  /api/clases-servicio
POST/PUT/DELETE  /api/empleados
POST             /api/empleados/{id}/reset-password
DELETE           /api/recetas/{id}
PUT              /api/pagos/{id}

── Admin + Recepcionista ───────────────────────────────────
POST/PUT/DELETE  /api/personas
POST/PUT/DELETE  /api/servicios
POST/PUT/DELETE  /api/citas
GET/POST/DELETE  /api/pagos
GET              /api/cortes/activo
GET/POST/PUT/DELETE /api/cortes

── Admin + Dentista ────────────────────────────────────────
GET/POST/PUT     /api/recetas
```

### Cómo agregar un nuevo endpoint:

1. **Crear el Controller** en `app/Http/Controllers/NombreController.php`
2. **Crear el Resource** en `app/Http/Resources/NombreResource.php` (formato del JSON)
3. **Crear los Requests** en `app/Http/Requests/` (validaciones del body)
4. **Declarar la ruta** en `routes/api.php` dentro del grupo de middleware que corresponda

---

## Requests — validaciones de entrada

Viven en `app/Http/Requests/` (un archivo por operación, ej. `StoreCitaRequest`, `UpdateCitaRequest`).
Definen qué campos son obligatorios, qué tipo deben ser, y mensajes de error personalizados.
Si la validación falla, Laravel devuelve automáticamente un error 422 con detalle de los campos inválidos.

---

## Flujo de una petición típica

```
Frontend
   │
   ▼
routes/api.php          ← ¿Existe esta ruta? ¿Qué controller la maneja?
   │
   ▼
Middleware              ← ¿Tiene token válido? ¿Tiene el rol correcto?
   │
   ▼
FormRequest             ← ¿Los datos del body son válidos?
   │
   ▼
Controller              ← Lógica: consulta la BD, aplica reglas de negocio
   │
   ▼
Resource                ← Formatea el resultado como JSON limpio
   │
   ▼
Frontend recibe JSON
```
