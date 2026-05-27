<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecetaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->idReceta,
            'indicaciones'  => $this->indicaciones,
            'fechaRegistro' => optional($this->created_at)->toDateString(),
            'createdAt'     => optional($this->created_at)->toDateString(),
            'cita'          => $this->whenLoaded('cita', fn () => [
                'id'              => $this->cita->idCita,
                'fechaProgramada' => $this->cita->fechaProgramada,
                'hora'            => $this->cita->hora,
                'paciente'        => $this->cita->relationLoaded('persona') ? [
                    'id'             => $this->cita->persona->idPersona,
                    'nombreCompleto' => trim("{$this->cita->persona->nombre} {$this->cita->persona->apellidoP} {$this->cita->persona->apellidoM}"),
                ] : null,
                'servicio'        => $this->cita->relationLoaded('servicio') && $this->cita->servicio ? [
                    'id'       => $this->cita->servicio->idServicio,
                    'nombre'   => $this->cita->servicio->nombre,
                    'costo'    => $this->cita->servicio->costo,
                    'duracion' => $this->cita->servicio->duracion,
                ] : null,
                'dentista'        => $this->cita->relationLoaded('empleado') && $this->cita->empleado ? [
                    'id'             => $this->cita->empleado->idEmpleado,
                    'nombreCompleto' => $this->cita->empleado->relationLoaded('persona') && $this->cita->empleado->persona
                        ? trim("{$this->cita->empleado->persona->nombre} {$this->cita->empleado->persona->apellidoP} {$this->cita->empleado->persona->apellidoM}")
                        : $this->cita->empleado->usuario,
                ] : null,
            ]),
        ];
    }
}
