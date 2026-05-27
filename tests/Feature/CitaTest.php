<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Empleado;
use App\Models\Persona;
use App\Models\Servicio;
use App\Models\TipoEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class CitaTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function payload(array $overrides = []): array
    {
        $persona  = Persona::factory()->create();
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentista = $this->crearDentista();

        return array_merge([
            'idPersona'       => $persona->idPersona,
            'idServicio'      => $servicio->idServicio,
            'idEmpleado'      => $dentista->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora'            => '10:00',
            'motivo'          => 'Revision general',
        ], $overrides);
    }

    public function test_recepcionista_puede_crear_cita(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertCreated()
            ->assertJsonPath('data.dentista.id', $payload['idEmpleado']);
    }

    public function test_crear_cita_requiere_dentista(): void
    {
        $payload = $this->payload();
        unset($payload['idEmpleado']);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idEmpleado']);
    }

    public function test_crear_cita_permite_motivo_vacio(): void
    {
        $payload = $this->payload(['motivo' => null]);

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertCreated()
            ->assertJsonPath('data.motivo', '');
    }

    public function test_crear_cita_rechaza_empleado_que_no_es_dentista(): void
    {
        $recepcionista = $this->crearRecepcionista();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload(['idEmpleado' => $recepcionista->idEmpleado]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idEmpleado']);
    }

    public function test_crear_cita_rechaza_dentista_inactivo(): void
    {
        $dentista = $this->crearDentista();
        $dentista->update(['estado' => false]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload(['idEmpleado' => $dentista->idEmpleado]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idEmpleado']);
    }

    public function test_crear_cita_acepta_tipo_dentista_normalizado(): void
    {
        $tipo = TipoEmpleado::factory()->create(['nombre' => ' DENTISTA ']);
        $dentista = Empleado::factory()->create(['idTipoEmpleado' => $tipo->idTipoEmpleado]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload(['idEmpleado' => $dentista->idEmpleado]))
            ->assertCreated()
            ->assertJsonPath('data.dentista.id', $dentista->idEmpleado);
    }

    public function test_dentista_no_puede_crear_cita(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson('/api/citas', $this->payload())
            ->assertForbidden();
    }

    public function test_colision_de_horario_por_dentista_retorna_422(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertCreated();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hora']);
    }

    public function test_mismo_horario_es_permitido_para_distinto_dentista(): void
    {
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentistaUno = $this->crearDentista();
        $dentistaDos = $this->crearDentista();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentistaUno->idEmpleado,
            ]))->assertCreated();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentistaDos->idEmpleado,
            ]))->assertCreated();
    }

    public function test_crear_cita_rechaza_traslape_por_duracion(): void
    {
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentista = $this->crearDentista();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentista->idEmpleado,
                'hora' => '10:00',
            ]))->assertCreated();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentista->idEmpleado,
                'hora' => '10:30',
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['hora']);
    }

    public function test_crear_cita_permite_horario_consecutivo(): void
    {
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentista = $this->crearDentista();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentista->idEmpleado,
                'hora' => '10:00',
            ]))->assertCreated();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentista->idEmpleado,
                'hora' => '11:00',
            ]))->assertCreated();
    }

    public function test_cita_inactiva_no_bloquea_disponibilidad(): void
    {
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentista = $this->crearDentista();

        Cita::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentista->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '10:00',
            'estado' => false,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentista->idEmpleado,
                'hora' => '10:00',
            ]))->assertCreated();
    }

    public function test_update_manteniendo_datos_de_agenda_no_colisiona_consigo_misma(): void
    {
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentista = $this->crearDentista();
        $cita = Cita::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentista->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '10:00',
            'estado' => true,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/citas/{$cita->idCita}", [
                'idServicio' => $servicio->idServicio,
                'idEmpleado' => $dentista->idEmpleado,
                'fechaProgramada' => '2027-01-15',
                'hora' => '10:00',
            ])->assertOk();
    }

    public function test_update_sin_cambiar_agenda_sigue_funcionando(): void
    {
        $cita = Cita::factory()->create(['estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/citas/{$cita->idCita}", ['motivo' => 'Control actualizado'])
            ->assertOk()
            ->assertJsonPath('data.motivo', 'Control actualizado');
    }

    public function test_update_rechaza_horario_de_otra_cita_del_mismo_dentista(): void
    {
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentista = $this->crearDentista();

        Cita::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentista->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '10:00',
            'estado' => true,
        ]);
        $cita = Cita::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentista->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '12:00',
            'estado' => true,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/citas/{$cita->idCita}", ['hora' => '10:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hora']);
    }

    public function test_update_permite_horario_ocupado_por_otro_dentista(): void
    {
        $servicio = Servicio::factory()->create(['duracion' => '01:00:00']);
        $dentistaUno = $this->crearDentista();
        $dentistaDos = $this->crearDentista();

        Cita::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentistaUno->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '10:00',
            'estado' => true,
        ]);
        $cita = Cita::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentistaDos->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '12:00',
            'estado' => true,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/citas/{$cita->idCita}", ['hora' => '10:00'])
            ->assertOk();
    }

    public function test_update_rechaza_traslape_por_duracion_del_nuevo_servicio(): void
    {
        $servicioCorto = Servicio::factory()->create(['duracion' => '00:30:00']);
        $servicioLargo = Servicio::factory()->create(['duracion' => '02:00:00']);
        $dentista = $this->crearDentista();

        Cita::factory()->create([
            'idServicio' => $servicioCorto->idServicio,
            'idEmpleado' => $dentista->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '10:00',
            'estado' => true,
        ]);
        $cita = Cita::factory()->create([
            'idServicio' => $servicioCorto->idServicio,
            'idEmpleado' => $dentista->idEmpleado,
            'fechaProgramada' => '2027-01-15',
            'hora' => '08:00',
            'estado' => true,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/citas/{$cita->idCita}", [
                'idServicio' => $servicioLargo->idServicio,
                'hora' => '09:00',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['hora']);
    }

    public function test_cita_historica_sin_dentista_no_rompe_listado(): void
    {
        Cita::factory()->create(['idEmpleado' => null, 'estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/citas')
            ->assertOk()
            ->assertJsonPath('data.0.dentista', null);
    }

    public function test_index_retorna_dentista_anidado(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas', $payload)
            ->assertCreated();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/citas?fecha=2027-01-15')
            ->assertOk()
            ->assertJsonPath('data.0.dentista.id', $payload['idEmpleado']);
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
