<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Persona;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class CitaTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function payload(array $overrides = []): array
    {
        $persona  = Persona::factory()->create();
        $servicio = Servicio::factory()->create();

        return array_merge([
            'idPersona'       => $persona->idPersona,
            'idServicio'      => $servicio->idServicio,
            'fechaProgramada' => '2027-01-15',
            'hora'            => '10:00',
            'motivo'          => 'Revisión general',
        ], $overrides);
    }

    public function test_recepcionista_puede_crear_cita(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/citas', $this->payload())
            ->assertCreated();
    }

    public function test_dentista_no_puede_crear_cita(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson('/api/citas', $this->payload())
            ->assertForbidden();
    }

    public function test_colision_de_horario_retorna_422(): void
    {
        $payload = $this->payload();

        // Primera cita — debe crearse bien
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertCreated();

        // Segunda cita con mismo servicio+fecha+hora — debe fallar
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hora']);
    }

    public function test_filtro_por_fecha(): void
    {
        $servicio = Servicio::factory()->create();
        $persona  = Persona::factory()->create();

        Cita::factory()->create([
            'idPersona'       => $persona->idPersona,
            'idServicio'      => $servicio->idServicio,
            'fechaProgramada' => '2027-06-10',
            'estado'          => true,
        ]);
        Cita::factory()->create([
            'idPersona'       => $persona->idPersona,
            'idServicio'      => $servicio->idServicio,
            'fechaProgramada' => '2027-06-20',
            'estado'          => true,
        ]);

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/citas?fecha=2027-06-10');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filtro_por_paciente_id(): void
    {
        $servicio  = Servicio::factory()->create();
        $persona1  = Persona::factory()->create();
        $persona2  = Persona::factory()->create();

        Cita::factory()->create(['idPersona' => $persona1->idPersona, 'idServicio' => $servicio->idServicio, 'estado' => true]);
        Cita::factory()->create(['idPersona' => $persona2->idPersona, 'idServicio' => $servicio->idServicio, 'estado' => true]);

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/citas?paciente_id={$persona1->idPersona}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filtro_por_servicio_id(): void
    {
        $persona   = Persona::factory()->create();
        $servicio1 = Servicio::factory()->create();
        $servicio2 = Servicio::factory()->create();

        Cita::factory()->create(['idPersona' => $persona->idPersona, 'idServicio' => $servicio1->idServicio, 'estado' => true]);
        Cita::factory()->create(['idPersona' => $persona->idPersona, 'idServicio' => $servicio2->idServicio, 'estado' => true]);

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/citas?servicio_id={$servicio1->idServicio}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_retorna_solo_citas_activas(): void
    {
        $servicio = Servicio::factory()->create();
        $persona  = Persona::factory()->create();

        Cita::factory()->create(['idPersona' => $persona->idPersona, 'idServicio' => $servicio->idServicio, 'estado' => true]);
        Cita::factory()->create(['idPersona' => $persona->idPersona, 'idServicio' => $servicio->idServicio, 'estado' => false]);

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/citas');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
