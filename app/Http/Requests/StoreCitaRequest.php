<?php

namespace App\Http\Requests;

use App\Services\DisponibilidadCitaService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idPersona'       => 'required|integer|exists:personas,idPersona',
            'idServicio'      => 'required|integer|exists:servicios,idServicio',
            'idEmpleado'      => 'required|integer|exists:empleados,idEmpleado',
            'fechaProgramada' => 'required|date',
            'hora'            => 'required|date_format:H:i',
            'duracion'        => 'nullable|date_format:H:i:s',
            'motivo'          => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $disponibilidad = app(DisponibilidadCitaService::class);

        $validator->after(function ($validator) use ($disponibilidad) {
            if (! $this->filled('idEmpleado') || $validator->errors()->has('idEmpleado')) {
                return;
            }

            if (! $disponibilidad->esDentistaActivo((int) $this->idEmpleado)) {
                $validator->errors()->add('idEmpleado', 'El dentista seleccionado no es valido.');

                return;
            }

            if (! $this->filled(['fechaProgramada', 'hora', 'idServicio'])
                || $validator->errors()->hasAny(['fechaProgramada', 'hora', 'idServicio'])) {
                return;
            }

            if ($disponibilidad->tieneTraslape(
                (int) $this->idEmpleado,
                $this->fechaProgramada,
                $this->hora,
                (int) $this->idServicio
            )) {
                $validator->errors()->add('hora', 'El dentista ya tiene una cita que se traslapa con este horario.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'idPersona.required'       => 'El paciente es obligatorio.',
            'idPersona.exists'         => 'El paciente no existe.',
            'idServicio.required'      => 'El servicio es obligatorio.',
            'idServicio.exists'        => 'El servicio no existe.',
            'idEmpleado.required'      => 'El dentista es obligatorio.',
            'idEmpleado.exists'        => 'El dentista no existe.',
            'fechaProgramada.required' => 'La fecha de la cita es obligatoria.',
            'fechaProgramada.date'     => 'La fecha no tiene un formato válido.',
            'hora.required'            => 'La hora es obligatoria.',
            'hora.date_format'         => 'La hora debe tener el formato HH:MM.',
            'duracion.date_format'     => 'La duración debe tener el formato HH:MM:SS.',
        ];
    }
}
