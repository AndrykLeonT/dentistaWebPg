<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaseServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->idClaseServicio,
            'nombre' => $this->nombre,
        ];
    }
}
