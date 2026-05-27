<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordEmpleadoRequest;
use App\Http\Requests\StoreEmpleadoRequest;
use App\Http\Requests\UpdateEmpleadoRequest;
use App\Http\Resources\EmpleadoResource;
use App\Models\Empleado;
use App\Models\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmpleadoController extends Controller
{
    public function index()
    {
        $query = Empleado::with('persona', 'tipoEmpleado');

        if (! $this->esAdmin(request()->user())) {
            $query->activos();
        }

        return EmpleadoResource::collection(
            $query->latest()->get()
        );
    }

    public function store(StoreEmpleadoRequest $request)
    {
        $validated = $request->validated();

        $empleado = DB::transaction(function () use ($validated) {
            $persona = Persona::create([
                'nombre'            => $validated['nombre'],
                'apellidoP'         => $validated['apellidoP'],
                'apellidoM'         => $validated['apellidoM'] ?? null,
                'celular'           => $validated['celular'],
                'correoElectronico' => $validated['correoElectronico'] ?? null,
                'fechaRegistro'     => now()->toDateString(),
                'estado'            => true,
            ]);

            return Empleado::create([
                'idPersona'        => $persona->idPersona,
                'idTipoEmpleado'   => $validated['idTipoEmpleado'],
                'usuario'          => $validated['usuario'],
                'rfc'              => $validated['rfc'] ?? null,
                'contraseña'       => Hash::make($validated['contraseña']),
                'palabraClave'     => Hash::make($validated['palabraClave']),
                'cambioContraseña' => false,
                'estado'           => true,
            ]);
        });

        return new EmpleadoResource($empleado->load('persona', 'tipoEmpleado'));
    }

    public function show(Empleado $empleado)
    {
        return new EmpleadoResource($empleado->load('persona', 'tipoEmpleado', 'pagos'));
    }

    public function update(UpdateEmpleadoRequest $request, Empleado $empleado)
    {
        $data = $request->validated();
        $usuarioActual = $request->user();

        if ($usuarioActual?->idEmpleado === $empleado->idEmpleado) {
            if (array_key_exists('estado', $data) && ! $data['estado']) {
                return response()->json(['message' => 'No puedes desactivar tu propio usuario.'], 422);
            }

            if (array_key_exists('idTipoEmpleado', $data) && (int) $data['idTipoEmpleado'] !== (int) $empleado->idTipoEmpleado) {
                return response()->json(['message' => 'No puedes cambiar tu propio rol.'], 422);
            }
        }

        if ($this->esUltimoAdminActivo($empleado, $data)) {
            return response()->json(['message' => 'Debe existir al menos un administrador activo.'], 422);
        }

        $datosPersona = array_intersect_key($data, array_flip([
            'nombre',
            'apellidoP',
            'apellidoM',
            'celular',
            'correoElectronico',
        ]));

        $datosEmpleado = array_diff_key($data, $datosPersona);

        if (isset($datosEmpleado['contraseña'])) {
            $datosEmpleado['contraseña'] = Hash::make($datosEmpleado['contraseña']);
        }

        if (isset($datosEmpleado['palabraClave'])) {
            $datosEmpleado['palabraClave'] = Hash::make($datosEmpleado['palabraClave']);
        }

        DB::transaction(function () use ($empleado, $datosPersona, $datosEmpleado) {
            if ($datosPersona) {
                $empleado->persona()->update($datosPersona);
            }

            if ($datosEmpleado) {
                $empleado->update($datosEmpleado);

                if (array_key_exists('estado', $datosEmpleado) && ! $datosEmpleado['estado']) {
                    $empleado->tokens()->delete();
                }
            }
        });

        return new EmpleadoResource($empleado->load('persona', 'tipoEmpleado'));
    }

    public function resetPassword(ResetPasswordEmpleadoRequest $request, Empleado $empleado)
    {
        DB::transaction(function () use ($request, $empleado) {
            $empleado->update([
                'contraseña'       => Hash::make($request->nuevaContraseña),
                'cambioContraseña' => true,
            ]);

            $empleado->tokens()->delete();
        });

        return response()->json(['message' => 'Contraseña restablecida. El empleado deberá cambiarla en su próximo inicio de sesión.']);
    }

    public function destroy(Empleado $empleado)
    {
        if (request()->user()?->idEmpleado === $empleado->idEmpleado) {
            return response()->json(['message' => 'No puedes desactivar tu propio usuario.'], 422);
        }

        if ($this->esUltimoAdminActivo($empleado, ['estado' => false])) {
            return response()->json(['message' => 'Debe existir al menos un administrador activo.'], 422);
        }

        DB::transaction(function () use ($empleado) {
            $empleado->update(['estado' => false]);
            $empleado->tokens()->delete();
        });

        return response()->json(null, 204);
    }

    private function esAdmin(?Empleado $empleado): bool
    {
        if (! $empleado) {
            return false;
        }

        $empleado->loadMissing('tipoEmpleado');

        return strtolower($empleado->tipoEmpleado?->nombre ?? '') === 'administrador';
    }

    private function esUltimoAdminActivo(Empleado $empleado, array $data): bool
    {
        $empleado->loadMissing('tipoEmpleado');

        $esAdmin = $this->esAdmin($empleado);
        $seDesactiva = array_key_exists('estado', $data) && ! $data['estado'];
        $cambiaRol = array_key_exists('idTipoEmpleado', $data) && (int) $data['idTipoEmpleado'] !== (int) $empleado->idTipoEmpleado;

        if (! $esAdmin || (! $seDesactiva && ! $cambiaRol)) {
            return false;
        }

        return Empleado::where('estado', true)
            ->whereHas('tipoEmpleado', fn ($query) => $query->where('nombre', 'Administrador'))
            ->where('idEmpleado', '!=', $empleado->idEmpleado)
            ->doesntExist();
    }
}
