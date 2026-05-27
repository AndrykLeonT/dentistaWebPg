<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsumoInventarioCitaController;
use App\Http\Controllers\ConsumoServicioController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ClaseServicioController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\CorteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\HistorialPacienteController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\ProductoInventarioController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\TipoEmpleadoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/recover-password-keyword', [AuthController::class, 'recoverPasswordKeyword'])
    ->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'empleado.activo'])->group(function () {

    // Auth (sin restricción de rol)
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/me',               [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Lectura libre (todos los roles autenticados)
    Route::apiResource('tipos-empleado',  TipoEmpleadoController::class)->only(['index', 'show']);
    Route::apiResource('clases-servicio', ClaseServicioController::class)->only(['index', 'show']);
    Route::apiResource('personas',  PersonaController::class)->only(['index', 'show']);
    Route::apiResource('servicios', ServicioController::class)->only(['index', 'show']);
    Route::apiResource('empleados', EmpleadoController::class)->only(['index', 'show']);
    Route::apiResource('citas',     CitaController::class)->only(['index', 'show']);
    Route::get('personas/{persona}/historial-citas', [HistorialPacienteController::class, 'citas']);
    Route::get('dashboard/resumen', [DashboardController::class, 'resumen']);

    // Solo Admin
    Route::middleware('rol:admin')->group(function () {
        Route::apiResource('tipos-empleado',  TipoEmpleadoController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('clases-servicio', ClaseServicioController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('empleados', EmpleadoController::class)->only(['store', 'update', 'destroy']);
        Route::post('empleados/{empleado}/reset-password', [EmpleadoController::class, 'resetPassword']);
        Route::apiResource('inventario/consumos-servicio', ConsumoServicioController::class)
            ->parameters(['consumos-servicio' => 'consumoServicio']);
        Route::apiResource('recetas',   RecetaController::class)->only(['destroy']);
        Route::apiResource('pagos',     PagoController::class)->only(['update']);
    });

    // Admin + Recepcionista
    Route::middleware('rol:admin,recepcionista')->group(function () {
        Route::apiResource('personas',  PersonaController::class)->only(['store', 'update', 'destroy']);
        Route::get('personas/{persona}/historial-pagos', [HistorialPacienteController::class, 'pagos']);
        Route::apiResource('servicios', ServicioController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('citas',     CitaController::class)->only(['store', 'update', 'destroy']);
        Route::post('citas/{cita}/consumir-inventario', [ConsumoInventarioCitaController::class, 'store']);
        Route::apiResource('pagos',     PagoController::class)->only(['index', 'show', 'store', 'destroy']);
        Route::apiResource('comprobantes', ComprobanteController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::apiResource('inventario/productos', ProductoInventarioController::class)
            ->parameters(['productos' => 'producto'])
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('inventario/movimientos', MovimientoInventarioController::class)
            ->only(['index', 'store']);
        // Ruta estática antes del apiResource para evitar que Laravel trate "activo" como {corte}
        Route::get('cortes/activo', [CorteController::class, 'activo']);
        Route::apiResource('cortes',    CorteController::class);
    });

    // Admin + Dentista
    Route::middleware('rol:admin,dentista')->group(function () {
        Route::apiResource('recetas', RecetaController::class)->only(['index', 'show', 'store', 'update']);
    });
});
