<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClaseServicioRequest;
use App\Http\Requests\UpdateClaseServicioRequest;
use App\Http\Resources\ClaseServicioResource;
use App\Models\ClaseServicio;

class ClaseServicioController extends Controller
{
    public function index()
    {
        return ClaseServicioResource::collection(ClaseServicio::activos()->get());
    }

    public function store(StoreClaseServicioRequest $request)
    {
        $clase = ClaseServicio::create($request->validated() + ['estado' => true]);

        return new ClaseServicioResource($clase);
    }

    public function show(ClaseServicio $claseServicio)
    {
        return new ClaseServicioResource($claseServicio);
    }

    public function update(UpdateClaseServicioRequest $request, ClaseServicio $claseServicio)
    {
        $claseServicio->update($request->validated());

        return new ClaseServicioResource($claseServicio);
    }

    public function destroy(ClaseServicio $claseServicio)
    {
        $claseServicio->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
