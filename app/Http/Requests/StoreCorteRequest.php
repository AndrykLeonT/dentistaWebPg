<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fDeCaja' => 'required|numeric|min:0',
            'fechaInicio' => 'prohibited',
            'fechaFin' => 'prohibited',
            'tEfectivo' => 'prohibited',
            'tTarjeta' => 'prohibited',
            'estado' => 'prohibited',
        ];
    }

    public function messages(): array
    {
        return [
            'fDeCaja.required' => 'El fondo de caja es obligatorio.',
            'fDeCaja.numeric'  => 'El fondo de caja debe ser un valor numérico.',
            'fDeCaja.min'      => 'El fondo de caja no puede ser negativo.',
        ];
    }
}
