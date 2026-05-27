<?php

namespace App\Http\Requests;

use App\Services\CajaService;
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
            'total'     => 'required|numeric|min:0.01',
            'efectivo'  => 'required|numeric|min:0',
            'tarjeta'   => 'required|numeric|min:0',
            'idEmpleado' => 'prohibited',
            'idCorte'    => 'prohibited',
            'pagado'     => 'prohibited',
        ];
    }

    public function withValidator($validator): void
    {
        $caja = app(CajaService::class);

        $validator->after(function ($validator) use ($caja) {
            if ($validator->errors()->hasAny(['total', 'efectivo', 'tarjeta'])) {
                return;
            }

            if (! $caja->pagoEstaLiquidado($this->total, $this->efectivo, $this->tarjeta)) {
                $validator->errors()->add('total', 'El pago debe liquidarse completamente: efectivo mas tarjeta debe ser igual al total.');
            }
        });
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
