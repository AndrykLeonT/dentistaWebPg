<?php

namespace Tests\Feature\Concerns;

use App\Models\Empleado;
use App\Models\TipoEmpleado;

trait CreatesEmployees
{
    protected function crearAdmin(): Empleado
    {
        $tipo = TipoEmpleado::factory()->administrador()->create();
        return Empleado::factory()->create(['idTipoEmpleado' => $tipo->idTipoEmpleado]);
    }

    protected function crearDentista(): Empleado
    {
        $tipo = TipoEmpleado::factory()->dentista()->create();
        return Empleado::factory()->create(['idTipoEmpleado' => $tipo->idTipoEmpleado]);
    }

    protected function crearRecepcionista(): Empleado
    {
        $tipo = TipoEmpleado::factory()->recepcionista()->create();
        return Empleado::factory()->create(['idTipoEmpleado' => $tipo->idTipoEmpleado]);
    }
}
