<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCorteRequest;
use App\Http\Requests\UpdateCorteRequest;
use App\Http\Resources\CorteResource;
use App\Models\Corte;

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

    public function store(StoreCorteRequest $request)
    {
        $hayAbierto = Corte::whereNull('fechaFin')->where('estado', 1)->exists();

        if ($hayAbierto) {
            return response()->json([
                'message' => 'Ya existe un corte de caja abierto. Ciérralo antes de abrir uno nuevo.',
            ], 422);
        }

        $corte = Corte::create($request->validated() + [
            'fechaInicio' => now(),
            'tEfectivo'   => 0,
            'tTarjeta'    => 0,
            'estado'      => true,
        ]);

        return new CorteResource($corte);
    }

    public function show(Corte $corte)
    {
        return new CorteResource($corte->load('pagos.persona'));
    }

    public function update(UpdateCorteRequest $request, Corte $corte)
    {
        $data = $request->validated();

        // Al cerrar el corte: calcular totales sumando los pagos activos del período
        if (isset($data['fechaFin']) && $corte->fechaFin === null) {
            $totales = $corte->pagos()
                ->where('estado', 1)
                ->selectRaw('COALESCE(SUM(efectivo), 0) as totalEfectivo, COALESCE(SUM(tarjeta), 0) as totalTarjeta')
                ->first();

            $data['tEfectivo'] = $totales->totalEfectivo;
            $data['tTarjeta']  = $totales->totalTarjeta;
        }

        $corte->update($data);

        return new CorteResource($corte->load('pagos.persona'));
    }

    public function destroy(Corte $corte)
    {
        $corte->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
