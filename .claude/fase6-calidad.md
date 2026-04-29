# Fase 6 — Decisiones de Calidad y Cierre

> Fecha: 2026-04-28

---

## Tests Feature

### Estructura

```
tests/Feature/
  Concerns/
    CreatesEmployees.php   ← trait compartido con helpers de rol
  AuthTest.php             ← 8 casos: login, logout, me, changePassword
  PersonaTest.php          ← 6 casos: CRUD, búsqueda, roles
  CitaTest.php             ← 6 casos: CRUD, filtros, colisión
  RecetaTest.php           ← 7 casos: CRUD, duplicado, acceso por rol
  CorteTest.php            ← 6 casos: abrir/cerrar, activo, totales
  PagoTest.php             ← 5 casos: registrar, idEmpleado, corte abierto
  EmpleadoTest.php         ← 8 casos: CRUD, reset-password, roles
```

### Convenciones de tests

- Todos usan `RefreshDatabase` — MySQL envuelve cada test en una transacción que se revierte.
- Autenticación con `$this->actingAs($empleado, 'sanctum')` — no genera tokens reales, usa el guard directamente.
- El trait `CreatesEmployees` crea `TipoEmpleado` con el nombre correcto (`Administrador`, `Dentista`, `Recepcionista`) para que `CheckRol` funcione.
- La contraseña en `EmpleadoFactory` es `bcrypt('password123')` — usada en tests de login.

### Cómo correr los tests

```bash
php artisan test
# o solo los feature tests:
php artisan test --testsuite=Feature
# o un módulo específico:
php artisan test tests/Feature/CorteTest.php
```

**Prerequisito:** La base de datos debe estar migrada (`php artisan migrate`) antes del primer run. `RefreshDatabase` con MySQL no re-migra, solo usa transacciones.

---

## N+1 corregido

**`RecetaController::index`**: cambiado de `with('cita')` a `with('cita.persona')`.

**Razón:** `RecetaResource` accede a `$this->cita->persona` para construir el sub-objeto `paciente`. Sin el eager load de `persona`, Eloquent lanzaba una query por cada receta en el listado.

---

## Seguridad — revisión

| Área | Estado | Notas |
|---|---|---|
| Inyección SQL | ✅ Seguro | Todo usa Eloquent/QueryBuilder, sin raw SQL con input del usuario |
| XSS | ✅ N/A | API JSON pura, no renderiza HTML |
| Contraseñas | ✅ Seguro | `Hash::make()` (bcrypt) en store y update; `Hash::check()` en login |
| Campos sensibles | ✅ Ocultos | `contraseña`, `palabraClave` en `$hidden` del modelo y fuera de Resources |
| Autorización | ✅ Por rol | `CheckRol` middleware en todas las rutas de escritura |
| `scopeActivos()` | ✅ Consistente | Todos los index retornan solo `estado=1` |
| `idEmpleado` en pagos | ✅ Del token | Extraído de `$request->user()`, no del body |
| Corte abierto único | ✅ Validado | `store` verifica `fechaFin IS NULL` antes de crear |

**Pendiente menor:** Rate limiting en `POST /api/login`. El throttle global de Laravel aplica, pero podría ser conveniente una regla más estricta en login para prevenir fuerza bruta.

---

## CORS para producción

El archivo `config/cors.php` lee `FRONTEND_URL` desde `.env`:

```env
# Desarrollo
FRONTEND_URL=http://localhost:5173

# Producción — cambiar al dominio real del frontend
FRONTEND_URL=https://mi-frontend.ejemplo.com
```

`supports_credentials: true` está activado. Si el frontend usa tokens Bearer (no cookies), puede cambiarse a `false` en producción para reducir la superficie de ataque.

`allowed_methods: ['*']` puede restringirse a `['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']` en producción.

---

## Pendiente aún

- **Documentación de API**: generar colección Postman o equivalente (OpenAPI/Swagger). El archivo `.claude/api-contracts.md` ya documenta todos los endpoints y puede usarse como base.
