<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\ProductoInventario;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    public function test_usuario_autenticado_consulta_dashboard_y_sin_token_recibe_401(): void
    {
        $this->getJson('/api/dashboard/resumen')->assertUnauthorized();

        $this->actingAs($this->crearDentista(), 'sanctum')
            ->getJson('/api/dashboard/resumen')
            ->assertOk()
            ->assertJsonStructure([
                'pacientesActivos',
                'citasHoy',
                'ingresosHoy',
                'productosBajoStock',
                'citasProximas',
                'alertasInventario',
            ]);
    }

    public function test_dashboard_devuelve_ceros_y_arreglos_vacios_sin_datos_de_negocio(): void
    {
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/dashboard/resumen')
            ->assertOk()
            ->assertJsonPath('pacientesActivos', 0)
            ->assertJsonPath('citasHoy', 0)
            ->assertJsonPath('ingresosHoy', 0)
            ->assertJsonPath('productosBajoStock', 0)
            ->assertJsonCount(0, 'citasProximas')
            ->assertJsonCount(0, 'alertasInventario');
    }

    public function test_dashboard_calcula_metricas_reales(): void
    {
        Persona::factory()->count(2)->create(['estado' => true]);
        Persona::factory()->create(['estado' => false]);
        $persona = Persona::factory()->create(['nombre' => 'Ana']);
        $servicio = Servicio::factory()->create(['nombre' => 'Consulta']);
        $dentista = $this->crearDentista();

        Cita::factory()->create([
            'idPersona' => $persona->idPersona,
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentista->idEmpleado,
            'fechaProgramada' => now()->toDateString(),
            'hora' => '09:00',
            'estado' => true,
        ]);
        Cita::factory()->create([
            'idPersona' => $persona->idPersona,
            'fechaProgramada' => now()->addDay()->toDateString(),
            'estado' => true,
        ]);
        Cita::factory()->create([
            'idPersona' => $persona->idPersona,
            'fechaProgramada' => now()->toDateString(),
            'estado' => false,
        ]);
        Pago::factory()->create([
            'idPersona' => $persona->idPersona,
            'fechaRegistro' => now()->toDateString(),
            'total' => '100.00',
            'estado' => true,
        ]);
        Pago::factory()->create([
            'idPersona' => $persona->idPersona,
            'fechaRegistro' => now()->toDateString(),
            'total' => '50.00',
            'estado' => true,
        ]);
        Pago::factory()->create([
            'idPersona' => $persona->idPersona,
            'fechaRegistro' => now()->toDateString(),
            'total' => '999.00',
            'estado' => false,
        ]);
        ProductoInventario::factory()->create([
            'nombre' => 'Guantes',
            'stockActual' => '2.00',
            'stockMinimo' => '5.00',
            'estado' => true,
        ]);
        ProductoInventario::factory()->create(['stockActual' => '10.00', 'stockMinimo' => '5.00', 'estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/dashboard/resumen')
            ->assertOk()
            ->assertJsonPath('pacientesActivos', 3)
            ->assertJsonPath('citasHoy', 1)
            ->assertJsonPath('ingresosHoy', 150)
            ->assertJsonPath('productosBajoStock', 1)
            ->assertJsonPath('citasProximas.0.servicio', 'Consulta')
            ->assertJsonPath('alertasInventario.0.nombre', 'Guantes');
    }
}
