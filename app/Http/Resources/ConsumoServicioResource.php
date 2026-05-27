<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsumoServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->idConsumoServicio,
            'idServicio' => $this->idServicio,
            'servicio' => $this->whenLoaded('servicio', fn () => $this->servicio->nombre),
            'idProductoInventario' => $this->idProductoInventario,
            'producto' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
            'cantidad' => $this->cantidad,
            'activo' => $this->estado,
        ];
    }
}
