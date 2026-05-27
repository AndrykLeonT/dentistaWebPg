<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicioRequest;
use App\Http\Requests\UpdateServicioRequest;
use App\Http\Resources\ServicioResource;
use App\Models\Empleado;
use App\Models\Servicio;

class ServicioController extends Controller
{
    public function index()
    {
        $query = Servicio::with('claseServicio');

        if (! $this->puedeAdministrarServicios(request()->user())) {
            $query->activos();
        }

        return ServicioResource::collection($query->orderBy('nombre')->get());
    }

    public function store(StoreServicioRequest $request)
    {
        $servicio = Servicio::create($request->validated() + ['estado' => true]);

        return new ServicioResource($servicio->load('claseServicio'));
    }

    public function show(Servicio $servicio)
    {
        return new ServicioResource($servicio->load('claseServicio'));
    }

    public function update(UpdateServicioRequest $request, Servicio $servicio)
    {
        $servicio->update($request->validated());

        return new ServicioResource($servicio->load('claseServicio'));
    }

    public function destroy(Servicio $servicio)
    {
        $servicio->update(['estado' => false]);

        return response()->json(null, 204);
    }

    private function puedeAdministrarServicios(?Empleado $empleado): bool
    {
        if (! $empleado) {
            return false;
        }

        $empleado->loadMissing('tipoEmpleado');

        return in_array(strtolower($empleado->tipoEmpleado?->nombre ?? ''), [
            'administrador',
            'admin',
            'recepcionista',
        ], true);
    }
}
