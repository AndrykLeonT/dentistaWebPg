<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'            => 'required|string|max:100',
            'apellidoP'         => 'required|string|max:100',
            'apellidoM'         => 'nullable|string|max:100',
            'celular'           => 'required|string|max:20',
            'correoElectronico' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('personas', 'correoElectronico'),
            ],
            'fechaRegistro'     => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'         => 'El nombre es obligatorio.',
            'apellidoP.required'      => 'El apellido paterno es obligatorio.',
            'celular.required'        => 'El celular es obligatorio.',
            'correoElectronico.email' => 'El correo no tiene un formato válido.',
        ];
    }
}
