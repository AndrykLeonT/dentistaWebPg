<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pago>
 */
class PagoFactory extends Factory
{
    public function definition(): array
    {
        $totalCentavos = fake()->numberBetween(50000, 800000);
        $efectivoCentavos = fake()->numberBetween(0, $totalCentavos);
        $tarjetaCentavos = $totalCentavos - $efectivoCentavos;

        return [
            'idPersona' => \App\Models\Persona::factory(),
            'idEmpleado' => \App\Models\Empleado::factory(),
            'idCorte' => \App\Models\Corte::factory(),
            'fechaRegistro' => fake()->date(),
            'total' => number_format($totalCentavos / 100, 2, '.', ''),
            'pagado' => true,
            'efectivo' => number_format($efectivoCentavos / 100, 2, '.', ''),
            'tarjeta' => number_format($tarjetaCentavos / 100, 2, '.', ''),
            'estado' => 1,
        ];
    }
}
