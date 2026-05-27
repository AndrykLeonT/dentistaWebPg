<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Comprobante;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class HistorialPacienteTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    public function test_admin_recepcionista_y_dentista_consultan_historial_de_citas(): void
    {
        $persona = Persona::factory()->create();
        $servicio = Servicio::factory()->create(['nombre' => 'Limpieza dental']);
        $dentistaEmpleado = $this->crearDentista();
        Cita::factory()->create([
            'idPersona' => $persona->idPersona,
            'idServicio' => $servicio->idServicio,
            'idEmpleado' => $dentistaEmpleado->idEmpleado,
            'fechaProgramada' => '2026-05-27',
            'hora' => '10:00',
            'motivo' => 'Control',
            'estado' => true,
        ]);

        foreach ([$this->crearAdmin(), $this->crearRecepcionista(), $this->crearDentista()] as $empleado) {
            $this->actingAs($empleado, 'sanctum')
                ->getJson("/api/personas/{$persona->idPersona}/historial-citas")
                ->assertOk()
                ->assertJsonPath('0.id', Cita::first()->idCita)
                ->assertJsonPath('0.fecha', '2026-05-27')
                ->assertJsonPath('0.hora', '10:00')
                ->assertJsonPath('0.estado', 'activa')
                ->assertJsonPath('0.servicio', 'Limpieza dental')
                ->assertJsonPath('0.observaciones', 'Control');
        }
    }

    public function test_historial_de_citas_requiere_token_y_persona_activa(): void
    {
        $persona = Persona::factory()->create();
        $inactiva = Persona::factory()->create(['estado' => false]);

        $this->getJson("/api/personas/{$persona->idPersona}/historial-citas")
            ->assertUnauthorized();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/personas/999999/historial-citas')
            ->assertNotFound();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/personas/{$inactiva->idPersona}/historial-citas")
            ->assertNotFound();
    }

    public function test_historial_de_citas_tolera_cita_historica_sin_dentista(): void
    {
        $persona = Persona::factory()->create();
        $cita = Cita::factory()->create([
            'idPersona' => $persona->idPersona,
            'idEmpleado' => null,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/personas/{$persona->idPersona}/historial-citas")
            ->assertOk()
            ->assertJsonPath('0.id', $cita->idCita)
            ->assertJsonPath('0.dentista', null);
    }

    public function test_admin_y_recepcionista_consultan_historial_de_pagos_con_folio_opcional(): void
    {
        $persona = Persona::factory()->create();
        $pagoConComprobante = Pago::factory()->create([
            'idPersona' => $persona->idPersona,
            'fechaRegistro' => '2026-05-27',
            'total' => '500.00',
            'efectivo' => '300.00',
            'tarjeta' => '200.00',
            'pagado' => true,
            'estado' => true,
        ]);
        Pago::factory()->create([
            'idPersona' => $persona->idPersona,
            'fechaRegistro' => '2026-05-26',
            'estado' => true,
        ]);
        Comprobante::create([
            'idPago' => $pagoConComprobante->idPago,
            'folio' => 'CMP-TEST-001',
            'fechaEmision' => now(),
            'total' => $pagoConComprobante->total,
            'efectivo' => $pagoConComprobante->efectivo,
            'tarjeta' => $pagoConComprobante->tarjeta,
            'estado' => true,
        ]);

        foreach ([$this->crearAdmin(), $this->crearRecepcionista()] as $empleado) {
            $this->actingAs($empleado, 'sanctum')
                ->getJson("/api/personas/{$persona->idPersona}/historial-pagos")
                ->assertOk()
                ->assertJsonPath('0.folioComprobante', 'CMP-TEST-001')
                ->assertJsonPath('0.estado', 'pagado')
                ->assertJsonPath('1.folioComprobante', null);
        }
    }

    public function test_historial_de_pagos_restringe_dentista_y_no_modifica_datos(): void
    {
        $persona = Persona::factory()->create();
        $pago = Pago::factory()->create(['idPersona' => $persona->idPersona, 'estado' => true]);

        $this->getJson("/api/personas/{$persona->idPersona}/historial-pagos")
            ->assertUnauthorized();

        $this->actingAs($this->crearDentista(), 'sanctum')
            ->getJson("/api/personas/{$persona->idPersona}/historial-pagos")
            ->assertForbidden();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/personas/999999/historial-pagos')
            ->assertNotFound();

        $inactiva = Persona::factory()->create(['estado' => false]);
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/personas/{$inactiva->idPersona}/historial-pagos")
            ->assertNotFound();

        $this->assertDatabaseHas('pagos', [
            'idPago' => $pago->idPago,
            'estado' => true,
        ]);
    }
}
