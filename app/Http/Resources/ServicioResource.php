<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->idServicio,
            'nombre'       => $this->nombre,
            'descripcion'  => $this->descripcion,
            'costo'        => $this->costo,
            'duracion'     => $this->duracion,
            'claseServicio' => $this->whenLoaded('claseServicio', fn () => new ClaseServicioResource($this->claseServicio)),
        ];
    }
}
