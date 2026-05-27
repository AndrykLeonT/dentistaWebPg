<?php

namespace App\Http\Requests;

use App\Models\Cita;
use App\Models\Empleado;
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
        $validator->after(function ($validator) {
            if ($this->filled('idEmpleado')) {
                $empleado = Empleado::with('tipoEmpleado')->find($this->idEmpleado);

                if (! $empleado || ! $empleado->estado || strtolower($empleado->tipoEmpleado?->nombre ?? '') !== 'dentista') {
                    $validator->errors()->add('idEmpleado', 'El dentista seleccionado no es valido.');
                }
            }

            if ($this->filled(['fechaProgramada', 'hora', 'idEmpleado'])) {
                $colision = Cita::where('fechaProgramada', $this->fechaProgramada)
                    ->where('hora', $this->hora)
                    ->where('idEmpleado', $this->idEmpleado)
                    ->where('estado', 1)
                    ->exists();

                if ($colision) {
                    $validator->errors()->add('hora', 'Ya existe una cita para ese dentista en esa fecha y hora.');
                }
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
