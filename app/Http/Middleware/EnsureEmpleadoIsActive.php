<?php

namespace App\Http\Middleware;

use App\Models\Empleado;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmpleadoIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $empleado = $request->user();

        if ($empleado instanceof Empleado && ! $empleado->estado) {
            $token = $empleado->currentAccessToken();

            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            return response()->json(['message' => 'La cuenta del empleado esta inactiva.'], 401);
        }

        return $next($request);
    }
}
