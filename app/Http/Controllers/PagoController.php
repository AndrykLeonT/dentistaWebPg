<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagoRequest;
use App\Http\Requests\UpdatePagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Services\CajaService;

class PagoController extends Controller
{
    public function index()
    {
        return PagoResource::collection(
            Pago::activos()->with('persona', 'empleado.persona', 'corte')->get()
        );
    }

    public function store(StorePagoRequest $request, CajaService $caja)
    {
        $pago = $caja->registrarPago($request->validated(), $request->user());

        if (! $pago) {
            return response()->json([
                'message' => 'No hay un corte de caja abierto. Abre un corte antes de registrar pagos.',
            ], 422);
        }

        return new PagoResource($pago->load('persona', 'empleado.persona', 'corte'));
    }

    public function show(Pago $pago)
    {
        return new PagoResource($pago->load('persona', 'empleado.persona', 'corte'));
    }

    public function update(UpdatePagoRequest $request, Pago $pago, CajaService $caja)
    {
        $pago = $caja->actualizarPago($pago, $request->validated());

        return new PagoResource($pago->load('persona', 'empleado.persona', 'corte'));
    }

    public function destroy(Pago $pago, CajaService $caja)
    {
        $caja->desactivarPago($pago);

        return response()->json(null, 204);
    }
}
