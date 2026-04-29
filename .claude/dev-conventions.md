# Convenciones de Desarrollo — Laravel Backend

## ⚠️ Reglas críticas — leer antes de crear cualquier modelo o migración

---

## 1. Primary Keys no estándar

**Todos** los modelos usan PKs camelCase propias, no el `id` por defecto de Laravel.

```php
// ✅ CORRECTO — siempre declarar $primaryKey
class Cita extends Model
{
    protected $primaryKey = 'idCita';
    public $incrementing = true;
    protected $keyType = 'int';
}

// ❌ INCORRECTO — Eloquent buscará columna 'id' que no existe
class Cita extends Model {}
```

### Mapa de PKs por modelo

| Modelo | $primaryKey |
|---|---|
| Persona | `idPersona` |
| Empleado | `idEmpleado` |
| TipoEmpleado | `idTipoEmpleado` |
| ClaseServicio | `idClaseServicio` |
| Servicio | `idServicio` |
| Cita | `idCita` |
| Receta | `idReceta` |
| Corte | `idCorte` |
| Pago | `idPago` |

---

## 2. Estructura base de un Modelo

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    protected $primaryKey = 'idCita';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idPersona',
        'idServicio',
        'fechaProgramada',
        'hora',
        'motivo',
        'estado',
    ];

    protected $casts = [
        'fechaProgramada' => 'date',
        'hora'            => 'string',
        'estado'          => 'boolean',
    ];

    // Scope por defecto
    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    // Relaciones
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'idServicio', 'idServicio');
    }

    public function receta()
    {
        return $this->hasOne(Receta::class, 'idCita', 'idCita');
    }
}
```

**Nota en relaciones:** siempre pasar la FK y la owner key explícitamente porque los nombres no siguen la convención de Laravel.

---

## 3. El modelo Empleado y autenticación

`Empleado` es el modelo de usuario autenticable (NO `User`).

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Empleado extends Authenticatable
{
    use HasApiTokens;

    protected $primaryKey = 'idEmpleado';

    protected $hidden = ['contraseña', 'palabraClave', 'remember_token'];

    // Laravel necesita saber cuál campo es el "password"
    public function getAuthPassword()
    {
        return $this->contraseña;
    }
}
```

Configurar en `config/auth.php`:
```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model'  => App\Models\Empleado::class,
    ],
],
```

---

## 4. Estructura de Controllers

Usar **Resource Controllers** bajo `app/Http/Controllers/Api/`.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCitaRequest;
use App\Models\Cita;
use Illuminate\Http\JsonResponse;

class CitaController extends Controller
{
    public function index(): JsonResponse
    {
        $citas = Cita::activos()->with(['persona', 'servicio'])->paginate(15);
        return response()->json(['data' => $citas]);
    }

    public function store(StoreCitaRequest $request): JsonResponse
    {
        $cita = Cita::create($request->validated() + ['estado' => 1]);
        return response()->json(['data' => $cita, 'message' => 'Cita creada'], 201);
    }

    public function destroy(Cita $cita): JsonResponse
    {
        $cita->update(['estado' => 0]);
        return response()->json(['message' => 'Cita cancelada'], 200);
    }
}
```

---

## 5.1 Patrón de doble eliminación

Todos los recursos tienen **dos operaciones de eliminación distintas**:

```
PATCH /{recurso}/{id}/desactivar  → eliminación lógica (estado=0)  — Admin + Recepcionista
DELETE /{recurso}/{id}            → eliminación física de BD        — Solo Admin
```

Implementar ambas en el mismo controller:

```php
// Eliminación lógica — disponible para admin + recepcionista
public function desactivar(Cita $cita): JsonResponse
{
    $cita->update(['estado' => 0]);
    return response()->json(['message' => 'Registro desactivado'], 200);
}

// Eliminación permanente — solo admin (protegido en middleware de rutas)
public function destroy(Cita $cita): JsonResponse
{
    $cita->delete(); // DELETE físico
    return response()->json(['message' => 'Registro eliminado permanentemente'], 200);
}
```

En `api.php`, las rutas se separan así:

```php
// Todos los roles con acceso pueden desactivar
Route::middleware('rol:administrador,recepcionista')->group(function () {
    Route::patch('citas/{cita}/desactivar', [CitaController::class, 'desactivar']);
});

// Solo admin puede eliminar permanentemente
Route::middleware('rol:administrador')->group(function () {
    Route::delete('citas/{cita}', [CitaController::class, 'destroy']);
});
```

---

## 5. Form Requests para validación

Toda validación va en `app/Http/Requests/`, nunca en el controller.

```php
class StoreCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización va en el middleware de roles
    }

    public function rules(): array
    {
        return [
            'idPersona'       => 'required|integer|exists:personas,idPersona',
            'idServicio'      => 'required|integer|exists:servicios,idServicio',
            'fechaProgramada' => 'required|date|after_or_equal:today',
            'hora'            => 'required|date_format:H:i',
            'motivo'          => 'nullable|string|max:500',
        ];
    }
}
```

---

## 6. Rutas en api.php

Agrupar por módulo con middleware:

```php
// routes/api.php

// Auth (público)
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Acceso: todos los roles autenticados
    Route::apiResource('pacientes', PacienteController::class)->only(['index', 'show']);
    Route::apiResource('citas', CitaController::class)->only(['index', 'show']);

    // Acceso: admin + recepcionista
    Route::middleware('rol:administrador,recepcionista')->group(function () {
        Route::apiResource('pacientes', PacienteController::class)->except(['index', 'show']);
        Route::apiResource('citas', CitaController::class)->except(['index', 'show']);
        Route::apiResource('pagos', PagoController::class);
        Route::apiResource('cortes', CorteController::class);
    });

    // Acceso: admin + dentista
    Route::middleware('rol:administrador,dentista')->group(function () {
        Route::get('citas/{cita}/receta', [RecetaController::class, 'show']);
        Route::post('citas/{cita}/receta', [RecetaController::class, 'store']);
        Route::put('recetas/{receta}', [RecetaController::class, 'update']);
    });

    // Acceso: solo admin
    Route::middleware('rol:administrador')->group(function () {
        Route::apiResource('empleados', EmpleadoController::class);
        Route::apiResource('servicios', ServicioController::class);
        Route::apiResource('clases-servicio', ClaseServicioController::class);
    });
});
```

---

## 7. Naming conventions

| Elemento | Convención | Ejemplo |
|---|---|---|
| Modelos | PascalCase singular | `Cita`, `TipoEmpleado` |
| Controllers | PascalCase + Controller | `CitaController` |
| Form Requests | Store/Update + Modelo + Request | `StoreCitaRequest` |
| Rutas | kebab-case plural | `/api/clases-servicio` |
| Migraciones | snake_case descripción | `create_citas_table` |
| Columnas DB | camelCase (ya existente) | `idPersona`, `fechaProgramada` |

---

## 8. Testing

- Tests corren contra la base de datos MySQL real (no SQLite)
- Usar `RefreshDatabase` con cuidado — puede borrar datos de desarrollo
- Preferir `DatabaseTransactions` trait para tests que no deben alterar el estado
- Colocar tests en `tests/Feature/Api/` organizados por módulo
