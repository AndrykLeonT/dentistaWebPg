<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string|max:500',
            'unidadMedida' => 'sometimes|string|max:50',
            'stockMinimo' => ['sometimes', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'costoUnitario' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'stockInicial' => 'prohibited',
            'stockActual' => 'prohibited',
            'estado' => 'prohibited',
        ];
    }
}
