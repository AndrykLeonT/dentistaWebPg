<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoEmpleadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->idTipoEmpleado,
            'nombre'      => $this->nombre,
            'descripcion' => $this->descripcion,
        ];
    }
}
