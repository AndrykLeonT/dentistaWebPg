<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmpleadoResource;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'usuario'    => 'required|string',
            'contraseña' => 'required|string',
        ]);

        $empleado = Empleado::where('usuario', $request->usuario)
            ->where('estado', 1)
            ->with('persona', 'tipoEmpleado')
            ->first();

        if (! $empleado || ! Hash::check($request->contraseña, $empleado->contraseña)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $token = $empleado->createToken('api-token')->plainTextToken;

        return response()->json([
            'token'                  => $token,
            'requiresPasswordChange' => (bool) $empleado->cambioContraseña,
            'empleado'               => new EmpleadoResource($empleado),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        return new EmpleadoResource($request->user()->load('persona', 'tipoEmpleado'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'contraseñaActual' => 'required|string',
            'nuevaContraseña'  => 'required|string|min:8|confirmed',
        ]);

        $empleado = $request->user();

        if (! Hash::check($request->contraseñaActual, $empleado->contraseña)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        $empleado->update([
            'contraseña'       => Hash::make($request->nuevaContraseña),
            'cambioContraseña' => false,
        ]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
