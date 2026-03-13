<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TipoEmpleado>
 */
class TipoEmpleadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->jobTitle(), // Inventa un nombre de puesto
            'descripcion' => fake()->sentence(), // Inventa una oración aleatoria
            'estado' => 1,
        ];
    }
}
