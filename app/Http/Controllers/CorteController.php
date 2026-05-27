<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCorteRequest;
use App\Http\Requests\UpdateCorteRequest;
use App\Http\Resources\CorteResource;
use App\Models\Corte;
use App\Services\CajaService;

class CorteController extends Controller
{
    public function index()
    {
        return CorteResource::collection(Corte::activos()->get());
    }

    public function activo()
    {
        $corte = Corte::whereNull('fechaFin')->where('estado', 1)->first();

        if (! $corte) {
            return response()->json(['message' => 'No hay ningún corte de caja abierto.'], 404);
        }

        return new CorteResource($corte->load('pagos.persona'));
    }

    public function store(StoreCorteRequest $request, CajaService $caja)
    {
        $corte = $caja->abrirCorte($request->validated());

        if (! $corte) {
            return response()->json([
                'message' => 'Ya existe un corte de caja abierto. Ciérralo antes de abrir uno nuevo.',
            ], 422);
        }

        return new CorteResource($corte);
    }

    public function show(Corte $corte)
    {
        return new CorteResource($corte->load('pagos.persona'));
    }

    public function update(UpdateCorteRequest $request, Corte $corte, CajaService $caja)
    {
        $corte = $caja->actualizarCorte($corte, $request->validated());

        return new CorteResource($corte->load('pagos.persona'));
    }

    public function destroy(Corte $corte, CajaService $caja)
    {
        $caja->desactivarCorte($corte);

        return response()->json(null, 204);
    }
}
