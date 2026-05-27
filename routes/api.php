<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ClaseServicioController;
use App\Http\Controllers\CorteController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\TipoEmpleadoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

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

    // Solo Admin
    Route::middleware('rol:admin')->group(function () {
        Route::apiResource('tipos-empleado',  TipoEmpleadoController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('clases-servicio', ClaseServicioController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('empleados', EmpleadoController::class)->only(['store', 'update', 'destroy']);
        Route::post('empleados/{empleado}/reset-password', [EmpleadoController::class, 'resetPassword']);
        Route::apiResource('recetas',   RecetaController::class)->only(['destroy']);
        Route::apiResource('pagos',     PagoController::class)->only(['update']);
    });

    // Admin + Recepcionista
    Route::middleware('rol:admin,recepcionista')->group(function () {
        Route::apiResource('personas',  PersonaController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('servicios', ServicioController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('citas',     CitaController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('pagos',     PagoController::class)->only(['index', 'show', 'store', 'destroy']);
        // Ruta estática antes del apiResource para evitar que Laravel trate "activo" como {corte}
        Route::get('cortes/activo', [CorteController::class, 'activo']);
        Route::apiResource('cortes',    CorteController::class);
    });

    // Admin + Dentista
    Route::middleware('rol:admin,dentista')->group(function () {
        Route::apiResource('recetas', RecetaController::class)->only(['index', 'show', 'store', 'update']);
    });
});
