<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Empleado>
 */
class EmpleadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idPersona' => \App\Models\Persona::factory(), 
            'idTipoEmpleado' => \App\Models\TipoEmpleado::factory(),
            'usuario' => fake()->unique()->userName(),
            'rfc' => fake()->unique()->bothify('????######???'), 
            'contraseña' => Hash::make('password123'), // Contraseña encriptada por seguridad
            'palabraClave' => Hash::make('palabra123'),
            'cambioContraseña' => 0,
            'estado' => 1,
        ];
    }
}
