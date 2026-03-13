<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaseServicio>
 */
class ClaseServicioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Ortodoncia', 'Limpieza', 'Cirugía', 'Endodoncia', 'Estética']),
            'estado' => 1,
        ];
    }
}
