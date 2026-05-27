<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComprobanteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'idComprobante' => $this->idComprobante,
            'folio' => $this->folio,
            'fechaEmision' => $this->fechaEmision,
            'estado' => $this->estado ? 'emitido' : 'cancelado',
            'observaciones' => $this->observaciones,
            'total' => $this->total,
            'efectivo' => $this->efectivo,
            'tarjeta' => $this->tarjeta,
            'pago' => $this->whenLoaded('pago', fn () => [
                'idPago' => $this->pago->idPago,
                'fechaRegistro' => $this->pago->fechaRegistro,
            ]),
            'paciente' => $this->when(
                $this->relationLoaded('pago') && $this->pago->relationLoaded('persona'),
                fn () => [
                    'id' => $this->pago->persona->idPersona,
                    'nombreCompleto' => trim("{$this->pago->persona->nombre} {$this->pago->persona->apellidoP} {$this->pago->persona->apellidoM}"),
                ]
            ),
            'cajero' => $this->when(
                $this->relationLoaded('pago') && $this->pago->relationLoaded('empleado'),
                fn () => [
                    'id' => $this->pago->empleado->idEmpleado,
                    'usuario' => $this->pago->empleado->usuario,
                ]
            ),
        ];
    }
}
