<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->idCita,
            'fechaRegistro'   => $this->fechaRegistro,
            'fechaProgramada' => $this->fechaProgramada,
            'hora'            => $this->hora,
            'duracion'        => $this->duracion,
            'motivo'          => $this->motivo,
            'paciente'        => $this->whenLoaded('persona', fn () => [
                'id'             => $this->persona->idPersona,
                'nombreCompleto' => trim("{$this->persona->nombre} {$this->persona->apellidoP} {$this->persona->apellidoM}"),
            ]),
            'servicio'        => $this->whenLoaded('servicio', fn () => [
                'id'     => $this->servicio->idServicio,
                'nombre' => $this->servicio->nombre,
                'costo'  => $this->servicio->costo,
                'duracion' => $this->servicio->duracion,
            ]),
            'dentista'        => $this->whenLoaded('empleado', fn () => $this->empleado ? [
                'id'             => $this->empleado->idEmpleado,
                'nombreCompleto' => $this->empleado->persona
                    ? trim("{$this->empleado->persona->nombre} {$this->empleado->persona->apellidoP} {$this->empleado->persona->apellidoM}")
                    : $this->empleado->usuario,
            ] : null),
            'receta'          => $this->whenLoaded('receta', fn () => new RecetaResource($this->receta)),
        ];
    }
}
