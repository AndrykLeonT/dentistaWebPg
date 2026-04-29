# Arquitectura General del Sistema

## Nombre del proyecto
**Sistema de Gestión de Consultorio Dental** (nombre interno: `dentista`)

## Estructura de repositorios

El proyecto está dividido en **dos repositorios separados**:

```
dentista-backend/    ← Laravel 10 (este repo)
dentista-frontend/   ← Vue 3 (repo separado)
```

Ambos repos se comunican exclusivamente vía **API REST con JSON**.
No hay Blade views para la app (excepto quizá una landing o ruta de salud).

---

## Stack Backend (este repo)

| Capa | Tecnología |
|---|---|
| Framework | Laravel 10 |
| Lenguaje | PHP 8.1+ |
| Base de datos | MySQL |
| Autenticación | Laravel Sanctum (tokens) |
| Build frontend | Vite (solo para assets internos si aplica) |
| Linter | Laravel Pint |
| Testing | PHPUnit contra MySQL real (SQLite desactivado) |

## Stack Frontend (repo separado)

| Capa | Tecnología |
|---|---|
| Framework | Vue 3 |
| Comunicación | Fetch / Axios contra API Laravel |
| Auth | Sanctum tokens via headers |

---

## Flujo de comunicación

```
[Vue 3 SPA]
    │
    │  HTTP + JSON
    │  Authorization: Bearer {token}
    ▼
[Laravel API]
    │
    │  Eloquent ORM
    ▼
[MySQL — dentista_db]
```

- El frontend nunca accede directamente a la base de datos.
- Toda lógica de negocio vive en el backend.
- El backend expone únicamente rutas bajo `/api/`.
- CORS debe estar configurado para aceptar el origen del frontend Vue.

---

## Estructura de carpetas clave (backend)

```
app/
  Http/
    Controllers/Api/     ← Todos los controllers de la API aquí
    Middleware/          ← Auth, roles
    Requests/            ← Form Requests para validación
  Models/                ← Modelos Eloquent (ver domain-model.md)
  Services/              ← Lógica de negocio compleja (opcional, por módulo)
  Policies/              ← Autorización por recurso y rol

database/
  migrations/            ← Migraciones existentes
  seeders/               ← Seeders en orden de dependencia

routes/
  api.php                ← TODAS las rutas van aquí
```

---

## Variables de entorno relevantes

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173   # origen Vue para CORS

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dentista_db
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:5173
```
