<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoInventarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->idMovimientoInventario,
            'tipoMovimiento' => $this->tipoMovimiento,
            'cantidad' => $this->cantidad,
            'stockAnterior' => $this->stockAnterior,
            'stockNuevo' => $this->stockNuevo,
            'motivo' => $this->motivo,
            'fechaMovimiento' => $this->fechaMovimiento,
            'producto' => $this->whenLoaded('producto', fn () => [
                'id' => $this->producto->idProductoInventario,
                'nombre' => $this->producto->nombre,
                'unidadMedida' => $this->producto->unidadMedida,
                'estado' => $this->producto->estado,
            ]),
            'empleado' => $this->whenLoaded('empleado', fn () => [
                'id' => $this->empleado->idEmpleado,
                'usuario' => $this->empleado->usuario,
            ]),
        ];
    }
}
