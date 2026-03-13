<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Receta>
 */
class RecetaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idCita' => \App\Models\Cita::factory(),
            'indicaciones' => fake()->paragraph(),
            'estado' => 1,
        ];
    }
}
