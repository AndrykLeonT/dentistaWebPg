<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->idPersona,
            'nombreCompleto'    => trim("{$this->nombre} {$this->apellidoP} {$this->apellidoM}"),
            'nombre'            => $this->nombre,
            'apellidoP'         => $this->apellidoP,
            'apellidoM'         => $this->apellidoM,
            'celular'           => $this->celular,
            'correoElectronico' => $this->correoElectronico,
            'fechaRegistro'     => $this->fechaRegistro,
            'citas'             => CitaResource::collection($this->whenLoaded('citas')),
            'pagos'             => PagoResource::collection($this->whenLoaded('pagos')),
        ];
    }
}
