<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCorteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fechaFin'  => 'sometimes|date|after_or_equal:fechaInicio',
            'fDeCaja'   => 'sometimes|numeric|min:0',
            'tEfectivo' => 'sometimes|numeric|min:0',
            'tTarjeta'  => 'sometimes|numeric|min:0',
            'correcto'  => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'fechaFin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'fDeCaja.numeric'         => 'El fondo de caja debe ser un valor numérico.',
        ];
    }
}
