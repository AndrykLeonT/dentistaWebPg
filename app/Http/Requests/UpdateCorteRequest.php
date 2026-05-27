<?php

namespace App\Http\Requests;

use Carbon\Carbon;
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
            'fechaFin'  => 'sometimes|date',
            'fDeCaja'   => 'sometimes|numeric|min:0',
            'tEfectivo' => 'prohibited',
            'tTarjeta'  => 'prohibited',
            'correcto'  => 'sometimes|boolean',
            'estado'    => 'prohibited',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $corte = $this->route('corte');

            if ($corte && $this->filled('fechaFin')
                && Carbon::parse($this->fechaFin)->lt($corte->fechaInicio)) {
                $validator->errors()->add('fechaFin', 'La fecha de fin debe ser igual o posterior a la fecha de inicio.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'fechaFin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'fDeCaja.numeric'         => 'El fondo de caja debe ser un valor numérico.',
        ];
    }
}
