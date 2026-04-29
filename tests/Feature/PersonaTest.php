<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class PersonaTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    public function test_index_retorna_solo_personas_activas(): void
    {
        // crearAdmin() genera internamente 1 persona activa (la del empleado)
        $admin = $this->crearAdmin();
        Persona::factory()->count(3)->create(['estado' => true]);
        Persona::factory()->create(['estado' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/personas');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_busqueda_por_nombre_filtra_resultados(): void
    {
        Persona::factory()->create(['nombre' => 'Lucía', 'estado' => true]);
        Persona::factory()->create(['nombre' => 'Carlos', 'estado' => true]);

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/personas?search=Luc');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Lucía');
    }

    public function test_busqueda_por_apellido_filtra_resultados(): void
    {
        Persona::factory()->create(['apellidoP' => 'Gutiérrez', 'estado' => true]);
        Persona::factory()->create(['apellidoP' => 'Ramírez', 'estado' => true]);

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/personas?search=Guti');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_puede_crear_persona(): void
    {
        $payload = [
            'nombre'            => 'Ana',
            'apellidoP'         => 'Torres',
            'apellidoM'         => 'Vega',
            'celular'           => '6121234567',
            'correoElectronico' => 'ana@test.com',
        ];

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/personas', $payload)
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Ana');
    }

    public function test_recepcionista_puede_crear_persona(): void
    {
        $payload = [
            'nombre'    => 'Pedro',
            'apellidoP' => 'Leal',
            'celular'   => '6129876543',
        ];

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/personas', $payload)
            ->assertCreated();
    }

    public function test_dentista_no_puede_crear_persona(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson('/api/personas', [
                'nombre'    => 'Test',
                'apellidoP' => 'Test',
                'celular'   => '6120000000',
            ])->assertForbidden();
    }

    public function test_destroy_desactiva_la_persona(): void
    {
        $persona = Persona::factory()->create(['estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->deleteJson("/api/personas/{$persona->idPersona}")
            ->assertNoContent();

        $this->assertFalse((bool) $persona->fresh()->estado);
    }

    public function test_show_retorna_persona_con_citas_y_pagos(): void
    {
        $persona = Persona::factory()->create();

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/personas/{$persona->idPersona}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'nombreCompleto', 'citas', 'pagos']]);
    }
}
