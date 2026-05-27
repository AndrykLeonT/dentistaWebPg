<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalCentavos = (int) round((float) $this->total * 100);
        $cubiertoCentavos = (int) round(((float) $this->efectivo + (float) $this->tarjeta) * 100);
        $pendienteCentavos = $totalCentavos - $cubiertoCentavos;

        return [
            'id'             => $this->idPago,
            'total'          => $this->total,
            'efectivo'       => $this->efectivo,
            'tarjeta'        => $this->tarjeta,
            'pendiente'      => $pendienteCentavos / 100,
            'pagado'         => $totalCentavos > 0 && $pendienteCentavos === 0,
            'fechaRegistro'  => $this->fechaRegistro,
            'paciente'       => $this->whenLoaded('persona', fn () => [
                'id'             => $this->persona->idPersona,
                'nombreCompleto' => trim("{$this->persona->nombre} {$this->persona->apellidoP} {$this->persona->apellidoM}"),
            ]),
            'empleado'       => $this->whenLoaded('empleado', fn () => [
                'id'      => $this->empleado->idEmpleado,
                'usuario' => $this->empleado->usuario,
                'nombre'  => $this->empleado->relationLoaded('persona')
                    ? trim("{$this->empleado->persona->nombre} {$this->empleado->persona->apellidoP}")
                    : null,
            ]),
            'corte'          => $this->whenLoaded('corte', fn () => [
                'id'          => $this->corte->idCorte,
                'fechaInicio' => $this->corte->fechaInicio,
                'fechaFin'    => $this->corte->fechaFin,
            ]),
        ];
    }
}
