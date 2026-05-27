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

    private function olvidarAutenticacionResuelta(): void
    {
        $this->app['auth']->forgetGuards();
    }

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

    public function test_token_previo_de_empleado_desactivado_ya_no_permite_acceso(): void
    {
        $empleado = $this->crearRecepcionista();
        $token = $empleado->createToken('sesion-empleado')->plainTextToken;
        $adminToken = $this->crearAdmin()->createToken('sesion-admin')->plainTextToken;

        $this->withToken($adminToken)
            ->deleteJson("/api/empleados/{$empleado->idEmpleado}")
            ->assertNoContent();

        $this->olvidarAutenticacionResuelta();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_empleado_desactivado_no_puede_ejecutar_operaciones_de_su_rol(): void
    {
        $empleado = $this->crearRecepcionista();
        $token = $empleado->createToken('sesion-empleado')->plainTextToken;
        $adminToken = $this->crearAdmin()->createToken('sesion-admin')->plainTextToken;

        $this->withToken($adminToken)
            ->deleteJson("/api/empleados/{$empleado->idEmpleado}")
            ->assertNoContent();

        $this->olvidarAutenticacionResuelta();

        $this->withToken($token)
            ->postJson('/api/personas', [
                'nombre'    => 'Paciente',
                'apellidoP' => 'Bloqueado',
                'celular'   => '6120000000',
            ])->assertUnauthorized();
    }

    public function test_middleware_revoca_token_si_empleado_esta_inactivo(): void
    {
        $empleado = $this->crearRecepcionista();
        $token = $empleado->createToken('sesion-activa')->plainTextToken;

        $empleado->update(['estado' => false]);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->assertCount(0, $empleado->tokens()->get());
    }

    public function test_token_expirado_no_permite_acceso(): void
    {
        $empleado = $this->crearAdmin();
        $token = $empleado->createToken('sesion-expirada')->plainTextToken;

        $empleado->tokens()->first()->forceFill([
            'created_at' => now()->subMinutes(config('sanctum.expiration') + 1),
        ])->save();

        $this->assertNotNull(config('sanctum.expiration'));

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
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

        $this->olvidarAutenticacionResuelta();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
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
