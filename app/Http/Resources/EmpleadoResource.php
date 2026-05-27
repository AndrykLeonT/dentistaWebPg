<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpleadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->idEmpleado,
            'usuario'     => $this->usuario,
            'rfc'         => $this->rfc,
            'estado'      => (bool) $this->estado,
            'cambioContraseña' => (bool) $this->cambioContraseña,
            'fechaRegistro' => optional($this->created_at)->toDateString(),
            'ultimoAcceso'  => null,
            'tipoEmpleado' => $this->whenLoaded('tipoEmpleado', fn () => new TipoEmpleadoResource($this->tipoEmpleado)),
            'persona'     => $this->whenLoaded('persona', fn () => [
                'id'                => $this->persona->idPersona,
                'nombreCompleto'    => trim("{$this->persona->nombre} {$this->persona->apellidoP} {$this->persona->apellidoM}"),
                'nombre'            => $this->persona->nombre,
                'apellidoP'         => $this->persona->apellidoP,
                'apellidoM'         => $this->persona->apellidoM,
                'celular'           => $this->persona->celular,
                'correoElectronico' => $this->persona->correoElectronico,
                'fechaRegistro'     => optional($this->persona->fechaRegistro)->toDateString(),
            ]),
        ];
    }
}
