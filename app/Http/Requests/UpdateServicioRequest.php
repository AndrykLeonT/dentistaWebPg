<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idClaseServicio' => 'sometimes|integer|exists:clase_servicios,idClaseServicio',
            'nombre'          => 'sometimes|string|max:150',
            'descripcion'     => 'nullable|string',
            'costo'           => 'sometimes|numeric|min:0',
            'duracion'        => 'sometimes|date_format:H:i:s',
        ];
    }

    public function messages(): array
    {
        return [
            'idClaseServicio.exists' => 'La clase de servicio no existe.',
            'costo.numeric'          => 'El costo debe ser un valor numérico.',
            'costo.min'              => 'El costo no puede ser negativo.',
            'duracion.date_format'   => 'La duración debe tener el formato HH:MM:SS.',
        ];
    }
}
