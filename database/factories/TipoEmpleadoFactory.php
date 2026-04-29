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
            'nombre'      => fake()->jobTitle(),
            'descripcion' => fake()->sentence(),
            'estado'      => 1,
        ];
    }

    public function administrador(): static
    {
        return $this->state(['nombre' => 'Administrador']);
    }

    public function dentista(): static
    {
        return $this->state(['nombre' => 'Dentista']);
    }

    public function recepcionista(): static
    {
        return $this->state(['nombre' => 'Recepcionista']);
    }
}
