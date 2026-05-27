<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empleado = $this->route('empleado');
        $id = $empleado?->idEmpleado;
        $personaId = $empleado?->idPersona;

        return [
            'nombre'           => 'sometimes|string|max:100',
            'apellidoP'        => 'sometimes|string|max:100',
            'apellidoM'        => 'nullable|string|max:100',
            'celular'          => 'sometimes|string|max:20',
            'correoElectronico' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('personas', 'correoElectronico')->ignore($personaId, 'idPersona'),
            ],
            'idTipoEmpleado'   => 'sometimes|integer|exists:tipo_empleados,idTipoEmpleado',
            'usuario'          => "sometimes|string|max:60|unique:empleados,usuario,{$id},idEmpleado",
            'rfc'              => 'nullable|string|max:13',
            'contraseña'       => 'sometimes|string|min:8',
            'palabraClave'     => 'sometimes|string|max:100',
            'cambioContraseña' => 'sometimes|boolean',
            'estado'           => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'idTipoEmpleado.exists' => 'El tipo de empleado no existe.',
            'usuario.unique'        => 'Ese nombre de usuario ya está en uso.',
            'contraseña.min'        => 'La contraseña debe tener al menos 8 caracteres.',
            'correoElectronico.email' => 'El correo no tiene un formato válido.',
            'correoElectronico.unique' => 'Ese correo ya está en uso.',
        ];
    }
}
