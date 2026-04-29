<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idCita'       => 'required|integer|exists:citas,idCita|unique:recetas,idCita',
            'indicaciones' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'idCita.required' => 'La cita es obligatoria.',
            'idCita.exists'   => 'La cita no existe.',
            'idCita.unique'   => 'Esta cita ya tiene una receta registrada.',
            'indicaciones.required' => 'Las indicaciones son obligatorias.',
        ];
    }
}
