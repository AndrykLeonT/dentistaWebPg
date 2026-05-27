<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovimientoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idProductoInventario' => 'required|integer|exists:productos_inventario,idProductoInventario',
            'tipoMovimiento' => ['required', 'string', Rule::in(['entrada', 'salida', 'ajuste'])],
            'cantidad' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'motivo' => 'nullable|string|max:500',
            'idEmpleado' => 'prohibited',
            'stockAnterior' => 'prohibited',
            'stockNuevo' => 'prohibited',
            'fechaMovimiento' => 'prohibited',
        ];
    }
}
