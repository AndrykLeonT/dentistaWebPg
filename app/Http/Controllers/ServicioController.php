<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicioRequest;
use App\Http\Requests\UpdateServicioRequest;
use App\Http\Resources\ServicioResource;
use App\Models\Servicio;

class ServicioController extends Controller
{
    public function index()
    {
        return ServicioResource::collection(Servicio::activos()->with('claseServicio')->get());
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
}
