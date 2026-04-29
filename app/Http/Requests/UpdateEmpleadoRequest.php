<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('empleado')?->idEmpleado;

        return [
            'idTipoEmpleado'   => 'sometimes|integer|exists:tipo_empleados,idTipoEmpleado',
            'usuario'          => "sometimes|string|max:60|unique:empleados,usuario,{$id},idEmpleado",
            'rfc'              => 'nullable|string|max:13',
            'contraseña'       => 'sometimes|string|min:8',
            'palabraClave'     => 'sometimes|string|max:100',
            'cambioContraseña' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'idTipoEmpleado.exists' => 'El tipo de empleado no existe.',
            'usuario.unique'        => 'Ese nombre de usuario ya está en uso.',
            'contraseña.min'        => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
