<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecetaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->idReceta,
            'indicaciones' => $this->indicaciones,
            'cita'         => $this->whenLoaded('cita', fn () => [
                'id'              => $this->cita->idCita,
                'fechaProgramada' => $this->cita->fechaProgramada,
                'hora'            => $this->cita->hora,
                'paciente'        => $this->cita->relationLoaded('persona') ? [
                    'id'             => $this->cita->persona->idPersona,
                    'nombreCompleto' => trim("{$this->cita->persona->nombre} {$this->cita->persona->apellidoP} {$this->cita->persona->apellidoM}"),
                ] : null,
            ]),
        ];
    }
}
