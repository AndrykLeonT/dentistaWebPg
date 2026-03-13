<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Persona>
 */
class PersonaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(), // Inventa un nombre
            'apellidoP' => fake()->lastName(), // Inventa un apellido paterno
            'apellidoM' => fake()->lastName(), // Inventa un apellido materno
            'celular' => fake()->numerify('##########'), // Inventa un número de 10 dígitos
            'correoElectronico' => fake()->unique()->safeEmail(), // Inventa un correo
            'fechaRegistro' => fake()->date(), // Inventa una fecha
            'estado' => 1,
        ];
    }
}
