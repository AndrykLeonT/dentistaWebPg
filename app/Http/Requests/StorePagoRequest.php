<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idPersona' => 'required|integer|exists:personas,idPersona',
            'total'     => 'required|numeric|min:0',
            'efectivo'  => 'required|numeric|min:0',
            'tarjeta'   => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'idPersona.required' => 'El paciente es obligatorio.',
            'idPersona.exists'   => 'El paciente no existe.',
            'total.required'     => 'El total es obligatorio.',
            'total.numeric'      => 'El total debe ser un valor numérico.',
            'efectivo.required'  => 'El monto en efectivo es obligatorio.',
            'tarjeta.required'   => 'El monto con tarjeta es obligatorio.',
        ];
    }
}
