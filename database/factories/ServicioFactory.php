<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Servicio>
 */
class ServicioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idClaseServicio' => \App\Models\ClaseServicio::factory(), // Crea una clase automáticamente si no existe
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
            'costo' => fake()->randomFloat(2, 300, 5000), // Costo entre 300 y 5000 con 2 decimales
            'duracion' => fake()->time('H:i'),
            'estado' => 1,
        ];
    }
}
