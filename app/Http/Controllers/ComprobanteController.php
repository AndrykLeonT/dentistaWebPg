<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComprobanteRequest;
use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;
use App\Services\ComprobanteService;
use Illuminate\Http\Request;

class ComprobanteController extends Controller
{
    public function index(Request $request)
    {
        $query = Comprobante::activos()->with('pago.persona', 'pago.empleado');

        if ($request->filled('idPago')) {
            $query->where('idPago', $request->integer('idPago'));
        }

        if ($request->filled('idPersona')) {
            $query->whereHas('pago', function ($pago) use ($request) {
                $pago->where('idPersona', $request->integer('idPersona'));
            });
        }

        return ComprobanteResource::collection($query->get());
    }

    public function store(StoreComprobanteRequest $request, ComprobanteService $comprobantes)
    {
        $comprobante = $comprobantes->emitir($request->validated());

        return new ComprobanteResource($comprobante);
    }

    public function show(Comprobante $comprobante)
    {
        $this->asegurarActivo($comprobante);

        return new ComprobanteResource(
            $comprobante->load('pago.persona', 'pago.empleado')
        );
    }

    public function destroy(Comprobante $comprobante, ComprobanteService $comprobantes)
    {
        $this->asegurarActivo($comprobante);
        $comprobantes->cancelar($comprobante);

        return response()->json(null, 204);
    }

    private function asegurarActivo(Comprobante $comprobante): void
    {
        abort_unless($comprobante->estado, 404);
    }
}
