<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsumirInventarioCitaRequest;
use App\Http\Resources\MovimientoInventarioResource;
use App\Models\Cita;
use App\Services\ConsumoInventarioCitaService;

class ConsumoInventarioCitaController extends Controller
{
    public function store(
        ConsumirInventarioCitaRequest $request,
        Cita $cita,
        ConsumoInventarioCitaService $service
    ) {
        $consumo = $service->consumir($cita, $request->user());

        return response()->json([
            'message' => 'Consumo de inventario aplicado correctamente.',
            'movimientos' => MovimientoInventarioResource::collection($consumo->movimientos),
        ]);
    }
}
