<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\TipoEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    public function test_login_con_credenciales_validas_retorna_token(): void
    {
        $empleado = $this->crearAdmin();

        $response = $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'requiresPasswordChange', 'empleado'])
            ->assertJsonPath('requiresPasswordChange', false);
    }

    public function test_login_con_contraseña_incorrecta_retorna_401(): void
    {
        $empleado = $this->crearAdmin();

        $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'incorrecta',
        ])->assertUnauthorized();
    }

    public function test_login_de_empleado_inactivo_retorna_401(): void
    {
        $empleado = $this->crearAdmin();
        $empleado->update(['estado' => false]);

        $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_login_con_cambio_de_contraseña_pendiente_retorna_flag(): void
    {
        $empleado = $this->crearAdmin();
        $empleado->update(['cambioContraseña' => true]);

        $response = $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('requiresPasswordChange', true);
    }

    public function test_logout_elimina_el_token_actual(): void
    {
        $empleado = $this->crearAdmin();
        $token    = $empleado->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada.');
    }

    public function test_me_retorna_datos_del_empleado_autenticado(): void
    {
        $empleado = $this->crearAdmin();

        $response = $this->actingAs($empleado, 'sanctum')
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('data.usuario', $empleado->usuario);
    }

    public function test_change_password_actualiza_la_contraseña(): void
    {
        $empleado = $this->crearAdmin();
        $empleado->update(['cambioContraseña' => true]);

        $this->actingAs($empleado, 'sanctum')
            ->postJson('/api/change-password', [
                'contraseñaActual'              => 'password123',
                'nuevaContraseña'               => 'nueva_pass_456',
                'nuevaContraseña_confirmation'  => 'nueva_pass_456',
            ])->assertOk();

        $this->assertFalse($empleado->fresh()->cambioContraseña);
    }

    public function test_change_password_falla_con_contraseña_actual_incorrecta(): void
    {
        $empleado = $this->crearAdmin();

        $this->actingAs($empleado, 'sanctum')
            ->postJson('/api/change-password', [
                'contraseñaActual'              => 'incorrecta',
                'nuevaContraseña'               => 'nueva_pass_456',
                'nuevaContraseña_confirmation'  => 'nueva_pass_456',
            ])->assertUnprocessable();
    }

    public function test_endpoints_protegidos_requieren_autenticacion(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
