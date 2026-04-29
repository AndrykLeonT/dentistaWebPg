<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idCorte'  => 'sometimes|integer|exists:cortes,idCorte',
            'total'    => 'sometimes|numeric|min:0',
            'efectivo' => 'sometimes|numeric|min:0',
            'tarjeta'  => 'sometimes|numeric|min:0',
            'pagado'   => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'idCorte.exists' => 'El corte de caja no existe.',
            'total.numeric'  => 'El total debe ser un valor numérico.',
        ];
    }
}
