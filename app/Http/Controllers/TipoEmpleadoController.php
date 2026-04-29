<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTipoEmpleadoRequest;
use App\Http\Requests\UpdateTipoEmpleadoRequest;
use App\Http\Resources\TipoEmpleadoResource;
use App\Models\TipoEmpleado;

class TipoEmpleadoController extends Controller
{
    public function index()
    {
        return TipoEmpleadoResource::collection(TipoEmpleado::activos()->get());
    }

    public function store(StoreTipoEmpleadoRequest $request)
    {
        $tipoEmpleado = TipoEmpleado::create($request->validated() + ['estado' => true]);

        return new TipoEmpleadoResource($tipoEmpleado);
    }

    public function show(TipoEmpleado $tipoEmpleado)
    {
        return new TipoEmpleadoResource($tipoEmpleado);
    }

    public function update(UpdateTipoEmpleadoRequest $request, TipoEmpleado $tipoEmpleado)
    {
        $tipoEmpleado->update($request->validated());

        return new TipoEmpleadoResource($tipoEmpleado);
    }

    public function destroy(TipoEmpleado $tipoEmpleado)
    {
        $tipoEmpleado->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
