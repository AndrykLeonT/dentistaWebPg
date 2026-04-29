<?php

namespace Tests\Feature;

use App\Models\Corte;
use App\Models\Pago;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class PagoTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    public function test_recepcionista_puede_registrar_pago_con_corte_abierto(): void
    {
        $corte   = Corte::factory()->abierto()->create();
        $persona = Persona::factory()->create();
        $recep   = $this->crearRecepcionista();

        $response = $this->actingAs($recep, 'sanctum')
            ->postJson('/api/pagos', [
                'idPersona' => $persona->idPersona,
                'total'     => 500.00,
                'efectivo'  => 200.00,
                'tarjeta'   => 300.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.corte.id', $corte->idCorte);
    }

    public function test_pago_toma_id_empleado_del_usuario_autenticado(): void
    {
        Corte::factory()->abierto()->create();
        $persona = Persona::factory()->create();
        $recep   = $this->crearRecepcionista();

        $response = $this->actingAs($recep, 'sanctum')
            ->postJson('/api/pagos', [
                'idPersona' => $persona->idPersona,
                'total'     => 300.00,
                'efectivo'  => 300.00,
                'tarjeta'   => 0.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.empleado.id', $recep->idEmpleado);
    }

    public function test_registrar_pago_sin_corte_abierto_retorna_422(): void
    {
        $persona = Persona::factory()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', [
                'idPersona' => $persona->idPersona,
                'total'     => 200.00,
                'efectivo'  => 200.00,
                'tarjeta'   => 0.00,
            ])->assertUnprocessable()
            ->assertJsonPath('message', 'No hay un corte de caja abierto. Abre un corte antes de registrar pagos.');
    }

    public function test_pago_calcula_campo_pendiente(): void
    {
        Corte::factory()->abierto()->create();
        $persona = Persona::factory()->create();

        $response = $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', [
                'idPersona' => $persona->idPersona,
                'total'     => 500.00,
                'efectivo'  => 200.00,
                'tarjeta'   => 100.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.pendiente', 200);
    }

    public function test_dentista_no_puede_ver_pagos(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->getJson('/api/pagos')
            ->assertForbidden();
    }
}
