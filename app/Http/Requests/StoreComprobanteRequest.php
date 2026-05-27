<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idPago' => 'required|integer|exists:pagos,idPago',
            'observaciones' => 'nullable|string|max:500',
            'folio' => 'prohibited',
            'fechaEmision' => 'prohibited',
            'total' => 'prohibited',
            'efectivo' => 'prohibited',
            'tarjeta' => 'prohibited',
            'estado' => 'prohibited',
        ];
    }
}
