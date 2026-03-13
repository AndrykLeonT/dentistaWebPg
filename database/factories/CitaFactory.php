<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cita>
 */
class CitaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idPersona' => \App\Models\Persona::factory(),
            'idServicio' => \App\Models\Servicio::factory(),
            'motivo' => fake()->sentence(),
            'fechaRegistro' => fake()->date(),
            'fechaProgramada' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'), // Citas a futuro
            'hora' => fake()->time('H:i'),
            'duracion' => fake()->time('H:i'),
            'estado' => 1,
        ];
    }
}
