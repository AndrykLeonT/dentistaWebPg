<?php

namespace App\Http\Requests;

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
            'fechaProgramada' => 'sometimes|date',
            'hora'            => 'sometimes|date_format:H:i',
            'duracion'        => 'nullable|date_format:H:i:s',
            'motivo'          => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'idPersona.exists'     => 'El paciente no existe.',
            'idServicio.exists'    => 'El servicio no existe.',
            'fechaProgramada.date' => 'La fecha no tiene un formato válido.',
            'hora.date_format'     => 'La hora debe tener el formato HH:MM.',
            'duracion.date_format' => 'La duración debe tener el formato HH:MM:SS.',
        ];
    }
}
