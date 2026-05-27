<?php

namespace App\Http\Requests;

use App\Services\CajaService;
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
            'idCorte'  => 'prohibited',
            'idEmpleado' => 'prohibited',
            'total'    => 'sometimes|numeric|min:0.01',
            'efectivo' => 'sometimes|numeric|min:0',
            'tarjeta'  => 'sometimes|numeric|min:0',
            'pagado'   => 'prohibited',
        ];
    }

    public function withValidator($validator): void
    {
        $caja = app(CajaService::class);

        $validator->after(function ($validator) use ($caja) {
            $pago = $this->route('pago');

            if (! $pago || ! $this->hasAny(['total', 'efectivo', 'tarjeta'])
                || $validator->errors()->hasAny(['total', 'efectivo', 'tarjeta'])) {
                return;
            }

            if (! $caja->pagoEstaLiquidado(
                $this->input('total', $pago->total),
                $this->input('efectivo', $pago->efectivo),
                $this->input('tarjeta', $pago->tarjeta)
            )) {
                $validator->errors()->add('total', 'El pago debe liquidarse completamente: efectivo mas tarjeta debe ser igual al total.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'idCorte.exists' => 'El corte de caja no existe.',
            'total.numeric'  => 'El total debe ser un valor numérico.',
        ];
    }
}
