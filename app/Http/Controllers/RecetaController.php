<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecetaRequest;
use App\Http\Requests\UpdateRecetaRequest;
use App\Http\Resources\RecetaResource;
use App\Models\Receta;

class RecetaController extends Controller
{
    public function index()
    {
        return RecetaResource::collection(Receta::activos()->with('cita.persona')->get());
    }

    public function store(StoreRecetaRequest $request)
    {
        $receta = Receta::create($request->validated() + ['estado' => true]);

        return new RecetaResource($receta->load('cita'));
    }

    public function show(Receta $receta)
    {
        return new RecetaResource($receta->load('cita.persona', 'cita.servicio'));
    }

    public function update(UpdateRecetaRequest $request, Receta $receta)
    {
        $receta->update($request->validated());

        return new RecetaResource($receta->load('cita'));
    }

    public function destroy(Receta $receta)
    {
        $receta->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
