<?php

namespace App\Http\Requests;

use App\Services\DisponibilidadCitaService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idPersona'       => 'sometimes|integer|exists:personas,idPersona',
            'idServicio'      => 'sometimes|integer|exists:servicios,idServicio',
            'idEmpleado'      => 'sometimes|required|integer|exists:empleados,idEmpleado',
            'fechaProgramada' => 'sometimes|date',
            'hora'            => 'sometimes|date_format:H:i',
            'duracion'        => 'nullable|date_format:H:i:s',
            'motivo'          => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $disponibilidad = app(DisponibilidadCitaService::class);

        $validator->after(function ($validator) use ($disponibilidad) {
            $cita = $this->route('cita');

            if (! $cita || ! $this->hasAny(['idEmpleado', 'fechaProgramada', 'hora', 'idServicio'])) {
                return;
            }

            $idEmpleado = $this->input('idEmpleado', $cita->idEmpleado);
            $fechaProgramada = $this->input('fechaProgramada', $cita->fechaProgramada?->format('Y-m-d'));
            $hora = $this->input('hora', $cita->hora);
            $idServicio = $this->input('idServicio', $cita->idServicio);

            if (! $idEmpleado) {
                $validator->errors()->add('idEmpleado', 'Debe asignar un dentista para modificar la agenda de la cita.');

                return;
            }

            if ($validator->errors()->hasAny(['idEmpleado', 'fechaProgramada', 'hora', 'idServicio'])) {
                return;
            }

            if (! $disponibilidad->esDentistaActivo((int) $idEmpleado)) {
                $validator->errors()->add('idEmpleado', 'El dentista seleccionado no es valido.');

                return;
            }

            if ($disponibilidad->tieneTraslape(
                (int) $idEmpleado,
                $fechaProgramada,
                $hora,
                (int) $idServicio,
                (int) $cita->idCita
            )) {
                $validator->errors()->add('hora', 'El dentista ya tiene una cita que se traslapa con este horario.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'idPersona.exists'     => 'El paciente no existe.',
            'idServicio.exists'    => 'El servicio no existe.',
            'idEmpleado.required'  => 'El dentista es obligatorio.',
            'idEmpleado.exists'    => 'El dentista no existe.',
            'fechaProgramada.date' => 'La fecha no tiene un formato valido.',
            'hora.date_format'     => 'La hora debe tener el formato HH:MM.',
            'duracion.date_format' => 'La duracion debe tener el formato HH:MM:SS.',
        ];
    }
}
