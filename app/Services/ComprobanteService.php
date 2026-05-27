<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComprobanteService
{
    public function __construct(private CajaService $caja)
    {
    }

    public function emitir(array $data): Comprobante
    {
        return DB::transaction(function () use ($data) {
            $pago = Pago::with('persona', 'empleado.persona', 'corte')
                ->lockForUpdate()
                ->findOrFail($data['idPago']);

            if (! $pago->estado) {
                throw ValidationException::withMessages([
                    'idPago' => 'No se puede emitir un comprobante para un pago inactivo.',
                ]);
            }

            if (! $this->caja->pagoEstaLiquidado($pago->total, $pago->efectivo, $pago->tarjeta)) {
                throw ValidationException::withMessages([
                    'idPago' => 'No se puede emitir un comprobante para un pago no liquidado.',
                ]);
            }

            if (Comprobante::where('idPago', $pago->idPago)->exists()) {
                throw ValidationException::withMessages([
                    'idPago' => 'Ya existe un comprobante para este pago.',
                ]);
            }

            $comprobante = Comprobante::create([
                'idPago' => $pago->idPago,
                'folio' => $this->generarFolio(),
                'fechaEmision' => now(),
                'total' => $pago->total,
                'efectivo' => $pago->efectivo,
                'tarjeta' => $pago->tarjeta,
                'estado' => true,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            return $comprobante->setRelation('pago', $pago);
        });
    }

    public function cancelar(Comprobante $comprobante): void
    {
        DB::transaction(function () use ($comprobante) {
            $actual = Comprobante::lockForUpdate()->findOrFail($comprobante->idComprobante);
            abort_unless($actual->estado, 404);

            $actual->update(['estado' => false]);
        });
    }

    private function generarFolio(): string
    {
        return 'CMP-' . now()->format('Ymd') . '-' . strtoupper((string) Str::ulid());
    }
}
