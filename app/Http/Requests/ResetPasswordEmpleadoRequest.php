<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nuevaContraseña' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'nuevaContraseña.required'  => 'La nueva contraseña es obligatoria.',
            'nuevaContraseña.min'       => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'nuevaContraseña.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}
