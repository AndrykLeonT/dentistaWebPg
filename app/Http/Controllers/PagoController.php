<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagoRequest;
use App\Http\Requests\UpdatePagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Corte;
use App\Models\Pago;

class PagoController extends Controller
{
    public function index()
    {
        return PagoResource::collection(
            Pago::activos()->with('persona', 'empleado.persona', 'corte')->get()
        );
    }

    public function store(StorePagoRequest $request)
    {
        $corteAbierto = Corte::whereNull('fechaFin')->where('estado', 1)->first();

        if (! $corteAbierto) {
            return response()->json([
                'message' => 'No hay un corte de caja abierto. Abre un corte antes de registrar pagos.',
            ], 422);
        }

        $pago = Pago::create($request->validated() + [
            'idEmpleado'    => $request->user()->idEmpleado,
            'idCorte'       => $corteAbierto->idCorte,
            'fechaRegistro' => now(),
            'pagado'        => true,
            'estado'        => true,
        ]);

        return new PagoResource($pago->load('persona', 'empleado.persona', 'corte'));
    }

    public function show(Pago $pago)
    {
        return new PagoResource($pago->load('persona', 'empleado.persona', 'corte'));
    }

    public function update(UpdatePagoRequest $request, Pago $pago)
    {
        $pago->update($request->validated());

        return new PagoResource($pago->load('persona', 'empleado.persona', 'corte'));
    }

    public function destroy(Pago $pago)
    {
        $pago->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
