<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRol
{
    private const MAP = [
        'administrador' => 'admin',
        'dentista'      => 'dentista',
        'recepcionista' => 'recepcionista',
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $empleado = $request->user();

        if (! $empleado) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $empleado->loadMissing('tipoEmpleado');

        $nombre    = $empleado->tipoEmpleado?->nombre ?? '';
        $rolActual = self::MAP[strtolower($nombre)] ?? null;

        if (! $rolActual || ! in_array($rolActual, $roles)) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }

        return $next($request);
    }
}
