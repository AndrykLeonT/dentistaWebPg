<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'            => 'sometimes|string|max:100',
            'apellidoP'         => 'sometimes|string|max:100',
            'apellidoM'         => 'nullable|string|max:100',
            'celular'           => 'sometimes|string|max:20',
            'correoElectronico' => 'nullable|email|max:150',
        ];
    }

    public function messages(): array
    {
        return [
            'correoElectronico.email' => 'El correo no tiene un formato válido.',
        ];
    }
}
