<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class PersonaTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana',
            'apellidoP' => 'Torres',
            'apellidoM' => 'Vega',
            'celular' => '6121234567',
            'correoElectronico' => 'ana@test.com',
        ], $overrides);
    }

    public function test_index_retorna_solo_personas_activas(): void
    {
        $admin = $this->crearAdmin();
        Persona::factory()->count(3)->create(['estado' => true]);
        Persona::factory()->create(['estado' => false]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/personas')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_busqueda_retorna_solo_personas_activas_que_coinciden(): void
    {
        Persona::factory()->create(['nombre' => 'Julia', 'estado' => true]);
        Persona::factory()->create(['nombre' => 'Julia', 'estado' => false]);
        Persona::factory()->create(['nombre' => 'Carlos', 'estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/personas?search=Julia')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Julia');
    }

    public function test_busqueda_por_apellido_filtra_resultados(): void
    {
        Persona::factory()->create(['apellidoP' => 'Gutierrez', 'estado' => true]);
        Persona::factory()->create(['apellidoP' => 'Ramirez', 'estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/personas?search=Guti')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_puede_crear_persona_activa(): void
    {
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/personas', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Ana');

        $this->assertTrue((bool) Persona::where('correoElectronico', 'ana@test.com')->firstOrFail()->estado);
    }

    public function test_crear_persona_rechaza_datos_invalidos_y_correo_duplicado(): void
    {
        Persona::factory()->create(['correoElectronico' => 'existente@test.com']);
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/personas', [
                'correoElectronico' => 'correo-invalido',
                'celular' => str_repeat('1', 21),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'apellidoP', 'celular', 'correoElectronico']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/personas', $this->payload([
                'correoElectronico' => 'existente@test.com',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correoElectronico']);
    }

    public function test_recepcionista_puede_crear_persona(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/personas', $this->payload([
                'nombre' => 'Pedro',
                'correoElectronico' => 'pedro@test.com',
            ]))
            ->assertCreated();
    }

    public function test_dentista_no_puede_crear_persona(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson('/api/personas', $this->payload())
            ->assertForbidden();
    }

    public function test_show_retorna_persona_activa_con_citas_y_pagos(): void
    {
        $persona = Persona::factory()->create(['estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/personas/{$persona->idPersona}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'nombreCompleto', 'citas', 'pagos']]);
    }

    public function test_show_de_persona_inactiva_retorna_404(): void
    {
        $persona = Persona::factory()->create(['estado' => false]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson("/api/personas/{$persona->idPersona}")
            ->assertNotFound();
    }

    public function test_actualizar_persona_activa_persiste_cambios(): void
    {
        $persona = Persona::factory()->create(['nombre' => 'Antes', 'estado' => true]);

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->patchJson("/api/personas/{$persona->idPersona}", ['nombre' => 'Despues'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Despues');

        $this->assertSame('Despues', $persona->fresh()->nombre);
    }

    public function test_actualizar_persona_rechaza_correo_duplicado(): void
    {
        Persona::factory()->create(['correoElectronico' => 'ocupado@test.com']);
        $persona = Persona::factory()->create(['correoElectronico' => 'libre@test.com']);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->patchJson("/api/personas/{$persona->idPersona}", [
                'correoElectronico' => 'ocupado@test.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correoElectronico']);
    }

    public function test_actualizar_persona_inactiva_retorna_404_y_no_modifica_datos(): void
    {
        $persona = Persona::factory()->create(['nombre' => 'Original', 'estado' => false]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->patchJson("/api/personas/{$persona->idPersona}", ['nombre' => 'Cambio'])
            ->assertNotFound();

        $this->assertSame('Original', $persona->fresh()->nombre);
    }

    public function test_destroy_hace_baja_logica_y_no_elimina_el_registro(): void
    {
        $persona = Persona::factory()->create(['estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->deleteJson("/api/personas/{$persona->idPersona}")
            ->assertNoContent();

        $this->assertDatabaseHas('personas', [
            'idPersona' => $persona->idPersona,
            'estado' => false,
        ]);
    }

    public function test_destroy_repetido_de_persona_inactiva_retorna_404(): void
    {
        $persona = Persona::factory()->create(['estado' => true]);
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/personas/{$persona->idPersona}")
            ->assertNoContent();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/personas/{$persona->idPersona}")
            ->assertNotFound();
    }

    public function test_persona_eliminada_no_reaparece_en_index(): void
    {
        $persona = Persona::factory()->create(['estado' => true]);
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/personas/{$persona->idPersona}")
            ->assertNoContent();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/personas')
            ->assertOk()
            ->assertJsonMissing(['id' => $persona->idPersona]);
    }

    public function test_personas_requieren_autenticacion_para_consulta(): void
    {
        $this->getJson('/api/personas')->assertUnauthorized();
    }
}
