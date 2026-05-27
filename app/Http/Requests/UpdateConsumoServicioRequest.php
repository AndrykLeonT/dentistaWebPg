<?php

namespace App\Http\Requests;

use App\Models\ConsumoServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateConsumoServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idServicio' => 'sometimes|required|integer|exists:servicios,idServicio',
            'idProductoInventario' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('productos_inventario', 'idProductoInventario')->where('estado', true),
            ],
            'cantidad' => ['sometimes', 'required', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
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

                $consumo = $this->route('consumoServicio');

                if (! $consumo instanceof ConsumoServicio) {
                    return;
                }

                $idServicio = $this->integer('idServicio') ?: $consumo->idServicio;
                $idProducto = $this->integer('idProductoInventario') ?: $consumo->idProductoInventario;

                $existe = ConsumoServicio::activos()
                    ->where('idConsumoServicio', '!=', $consumo->idConsumoServicio)
                    ->where('idServicio', $idServicio)
                    ->where('idProductoInventario', $idProducto)
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
