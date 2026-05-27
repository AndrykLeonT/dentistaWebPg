<?php

namespace App\Http\Requests;

use App\Models\Empleado;
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
        $validator->after(function ($validator) {
            if ($this->filled('idEmpleado')) {
                $empleado = Empleado::with('tipoEmpleado')->find($this->idEmpleado);

                if (! $empleado || ! $empleado->estado || strtolower($empleado->tipoEmpleado?->nombre ?? '') !== 'dentista') {
                    $validator->errors()->add('idEmpleado', 'El dentista seleccionado no es valido.');
                }
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
