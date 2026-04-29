<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordEmpleadoRequest;
use App\Http\Requests\StoreEmpleadoRequest;
use App\Http\Requests\UpdateEmpleadoRequest;
use App\Http\Resources\EmpleadoResource;
use App\Models\Empleado;
use App\Models\Persona;
use Illuminate\Support\Facades\Hash;

class EmpleadoController extends Controller
{
    public function index()
    {
        return EmpleadoResource::collection(
            Empleado::activos()->with('persona', 'tipoEmpleado')->get()
        );
    }

    public function store(StoreEmpleadoRequest $request)
    {
        $validated = $request->validated();

        $persona = Persona::create([
            'nombre'            => $validated['nombre'],
            'apellidoP'         => $validated['apellidoP'],
            'apellidoM'         => $validated['apellidoM'] ?? null,
            'celular'           => $validated['celular'],
            'correoElectronico' => $validated['correoElectronico'] ?? null,
            'fechaRegistro'     => now()->toDateString(),
            'estado'            => true,
        ]);

        $empleado = Empleado::create([
            'idPersona'        => $persona->idPersona,
            'idTipoEmpleado'   => $validated['idTipoEmpleado'],
            'usuario'          => $validated['usuario'],
            'rfc'              => $validated['rfc'] ?? null,
            'contraseña'       => Hash::make($validated['contraseña']),
            'palabraClave'     => Hash::make($validated['palabraClave']),
            'cambioContraseña' => false,
            'estado'           => true,
        ]);

        return new EmpleadoResource($empleado->load('persona', 'tipoEmpleado'));
    }

    public function show(Empleado $empleado)
    {
        return new EmpleadoResource($empleado->load('persona', 'tipoEmpleado', 'pagos'));
    }

    public function update(UpdateEmpleadoRequest $request, Empleado $empleado)
    {
        $data = $request->validated();

        if (isset($data['contraseña'])) {
            $data['contraseña'] = Hash::make($data['contraseña']);
        }

        if (isset($data['palabraClave'])) {
            $data['palabraClave'] = Hash::make($data['palabraClave']);
        }

        $empleado->update($data);

        return new EmpleadoResource($empleado->load('persona', 'tipoEmpleado'));
    }

    public function resetPassword(ResetPasswordEmpleadoRequest $request, Empleado $empleado)
    {
        $empleado->update([
            'contraseña'       => Hash::make($request->nuevaContraseña),
            'cambioContraseña' => true,
        ]);

        return response()->json(['message' => 'Contraseña restablecida. El empleado deberá cambiarla en su próximo inicio de sesión.']);
    }

    public function destroy(Empleado $empleado)
    {
        $empleado->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
