<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpleadoRequest extends FormRequest
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
            'idTipoEmpleado'    => 'required|integer|exists:tipo_empleados,idTipoEmpleado',
            'usuario'           => 'required|string|max:60|unique:empleados,usuario',
            'rfc'               => 'nullable|string|max:13',
            'contraseña'        => 'required|string|min:8',
            'palabraClave'      => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'         => 'El nombre es obligatorio.',
            'apellidoP.required'      => 'El apellido paterno es obligatorio.',
            'celular.required'        => 'El celular es obligatorio.',
            'correoElectronico.email' => 'El correo no tiene un formato válido.',
            'correoElectronico.unique' => 'Ese correo ya está en uso.',
            'idTipoEmpleado.required' => 'El tipo de empleado es obligatorio.',
            'idTipoEmpleado.exists'   => 'El tipo de empleado no existe.',
            'usuario.required'        => 'El usuario es obligatorio.',
            'usuario.unique'          => 'Ese nombre de usuario ya está en uso.',
            'contraseña.required'     => 'La contraseña es obligatoria.',
            'contraseña.min'          => 'La contraseña debe tener al menos 8 caracteres.',
            'palabraClave.required'   => 'La palabra clave es obligatoria.',
        ];
    }
}
