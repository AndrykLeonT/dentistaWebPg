<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

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
            'rfc' => fake()->unique()->bothify('????######???'), // Genera formato tipo RFC
            'contraseña' => bcrypt('password123'), // Contraseña encriptada por seguridad
            'palabraClave' => fake()->word(),
            'cambioContraseña' => 0,
            'estado' => 1,
        ];
    }
}
