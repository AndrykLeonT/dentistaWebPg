<?php

namespace App\Http\Requests;

use App\Models\ConsumoServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConsumoServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idServicio' => 'required|integer|exists:servicios,idServicio',
            'idProductoInventario' => [
                'required',
                'integer',
                Rule::exists('productos_inventario', 'idProductoInventario')->where('estado', true),
            ],
            'cantidad' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'estado' => 'prohibited',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $existe = ConsumoServicio::activos()
                    ->where('idServicio', $this->integer('idServicio'))
                    ->where('idProductoInventario', $this->integer('idProductoInventario'))
                    ->exists();

                if ($existe) {
                    $validator->errors()->add(
                        'idProductoInventario',
                        'Ya existe una regla activa para ese servicio y producto.'
                    );
                }
            },
        ];
    }
}
