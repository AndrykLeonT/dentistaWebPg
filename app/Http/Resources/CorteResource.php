<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->idCorte,
            'fechaInicio'     => $this->fechaInicio,
            'fechaFin'        => $this->fechaFin,
            'fDeCaja'         => $this->fDeCaja,
            'tEfectivo'       => $this->tEfectivo,
            'tTarjeta'        => $this->tTarjeta,
            'totalRecaudado'  => (float) $this->tEfectivo + (float) $this->tTarjeta,
            'correcto'        => $this->correcto,
            'pagos'           => PagoResource::collection($this->whenLoaded('pagos')),
        ];
    }
}
