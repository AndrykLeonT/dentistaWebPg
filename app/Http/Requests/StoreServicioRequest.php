<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idClaseServicio' => 'required|integer|exists:clase_servicios,idClaseServicio',
            'nombre'          => 'required|string|max:150',
            'descripcion'     => 'nullable|string',
            'costo'           => 'required|numeric|min:0',
            'duracion'        => 'required|date_format:H:i:s',
        ];
    }

    public function messages(): array
    {
        return [
            'idClaseServicio.required' => 'La clase de servicio es obligatoria.',
            'idClaseServicio.exists'   => 'La clase de servicio no existe.',
            'nombre.required'          => 'El nombre del servicio es obligatorio.',
            'costo.required'           => 'El costo es obligatorio.',
            'costo.numeric'            => 'El costo debe ser un valor numérico.',
            'costo.min'                => 'El costo no puede ser negativo.',
            'duracion.required'        => 'La duración es obligatoria.',
            'duracion.date_format'     => 'La duración debe tener el formato HH:MM:SS.',
        ];
    }
}
