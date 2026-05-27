<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\ClaseServicio;
use App\Models\Corte;
use App\Models\Empleado;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Receta;
use App\Models\Servicio;
use App\Models\TipoEmpleado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         * Seeding base del proyecto.
         * Se protege con count() para evitar duplicar datos aleatorios cada vez que se ejecute db:seed.
         */
        $this->seedIfEmpty(TipoEmpleado::class, 5);
        $this->seedIfEmpty(ClaseServicio::class, 5);
        $this->seedIfEmpty(Persona::class, 20);
        $this->seedIfEmpty(Corte::class, 10);
        $this->seedIfEmpty(Servicio::class, 15);
        $this->seedIfEmpty(Empleado::class, 5);
        $this->seedIfEmpty(Cita::class, 20);
        $this->seedIfEmpty(Receta::class, 20);
        $this->seedIfEmpty(Pago::class, 25);

        /*
         * Usuarios controlados para pruebas del frontend.
         * Estos registros son idempotentes: si ya existen, se actualizan.
         */
        $this->seedFrontendTestUsers();
    }

    private function seedIfEmpty(string $modelClass, int $count): void
    {
        if ($modelClass::query()->count() === 0) {
            $modelClass::factory($count)->create();
        }
    }

    private function seedFrontendTestUsers(): void
    {
        $empleadoModel = new Empleado();
        $empleadoTable = $empleadoModel->getTable();
        $empleadoPrimaryKey = $empleadoModel->getKeyName();

        if (!Schema::hasTable($empleadoTable)) {
            throw new \RuntimeException("No existe la tabla {$empleadoTable}. Revisa el modelo App\\Models\\Empleado.");
        }

        $empleadoColumns = Schema::getColumnListing($empleadoTable);

        $emailColumn = $this->firstExistingColumn($empleadoColumns, [
            'correoElectronico',
            'correoelectronico',
            'correo_electronico',
            'email',
            'correo',
            'mail',
            'usuario',
            'username',
        ]);

        $passwordColumn = $this->firstExistingColumn($empleadoColumns, [
            'password',
            'contraseña',
            'contrasena',
            'contrasenia',
            'clave',
            'claveAcceso',
            'clave_acceso',
            'password_hash',
        ]);

        if (!$emailColumn || !$passwordColumn) {
            throw new \RuntimeException(
                "No se encontró columna de correo o contraseña/password en {$empleadoTable}. " .
                'Columnas detectadas: ' . implode(', ', $empleadoColumns)
            );
        }

        $users = [
            [
                'nombre' => 'TEST Admin',
                'apellidoP' => 'DentalSys',
                'apellidoM' => 'Frontend',
                'correo' => 'test.admin@dentalsys.local',
                'password' => 'TestAdmin123!',
                'keyword' => 'ClaveAdmin123!',
                'rol' => 'admin',
                'telefono' => '6120000001',
            ],
            [
                'nombre' => 'TEST Recepcionista',
                'apellidoP' => 'DentalSys',
                'apellidoM' => 'Frontend',
                'correo' => 'test.recepcionista@dentalsys.local',
                'password' => 'TestRecep123!',
                'keyword' => 'ClaveRecep123!',
                'rol' => 'recepcionista',
                'telefono' => '6120000002',
            ],
            [
                'nombre' => 'TEST Dentista',
                'apellidoP' => 'DentalSys',
                'apellidoM' => 'Frontend',
                'correo' => 'test.dentista@dentalsys.local',
                'password' => 'TestDentista123!',
                'keyword' => 'ClaveDentista123!',
                'rol' => 'dentista',
                'telefono' => '6120000003',
            ],
        ];

        foreach ($users as $user) {
            $payload = $this->buildEmpleadoPayload($empleadoColumns, $emailColumn, $passwordColumn, $user);

            $existing = DB::table($empleadoTable)
                ->where($emailColumn, $user['correo'])
                ->first();

            if ($existing) {
                DB::table($empleadoTable)
                    ->where($empleadoPrimaryKey, $existing->{$empleadoPrimaryKey})
                    ->update($payload);

                continue;
            }

            /*
             * Se usa la factory del proyecto para respetar columnas obligatorias desconocidas.
             * Después se actualiza el registro con los datos TEST controlados.
             */
            $empleado = Empleado::factory()->create();

            DB::table($empleadoTable)
                ->where($empleadoPrimaryKey, $empleado->{$empleadoPrimaryKey})
                ->update($payload);
        }

        $this->command->info('Usuarios TEST creados/actualizados correctamente:');
        $this->command->line('Admin: test.admin@dentalsys.local / TestAdmin123!');
        $this->command->line('Recepcionista: test.recepcionista@dentalsys.local / TestRecep123!');
        $this->command->line('Dentista: test.dentista@dentalsys.local / TestDentista123!');
    }

    private function buildEmpleadoPayload(
        array $empleadoColumns,
        string $emailColumn,
        string $passwordColumn,
        array $user
    ): array {
        $payload = [];

        $this->putIfColumnExists($payload, $empleadoColumns, 'nombre', $user['nombre']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'nombres', $user['nombre']);

        $this->putIfColumnExists($payload, $empleadoColumns, 'apellidoP', $user['apellidoP']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'apellido_p', $user['apellidoP']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'apellidoPaterno', $user['apellidoP']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'apellido_paterno', $user['apellidoP']);

        $this->putIfColumnExists($payload, $empleadoColumns, 'apellidoM', $user['apellidoM']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'apellido_m', $user['apellidoM']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'apellidoMaterno', $user['apellidoM']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'apellido_materno', $user['apellidoM']);

        $this->putIfColumnExists($payload, $empleadoColumns, 'telefono', $user['telefono']);
        $this->putIfColumnExists($payload, $empleadoColumns, 'celular', $user['telefono']);

        $payload[$emailColumn] = $user['correo'];
        $payload[$passwordColumn] = Hash::make($user['password']);

        if (in_array('palabraClave', $empleadoColumns, true)) {
            $payload['palabraClave'] = Hash::make($user['keyword']);
        }

        if (in_array('estado', $empleadoColumns, true)) {
            $payload['estado'] = true;
        }

        if (in_array('activo', $empleadoColumns, true)) {
            $payload['activo'] = true;
        }

        if (in_array('requiereCambioPassword', $empleadoColumns, true)) {
            $payload['requiereCambioPassword'] = false;
        }

        if (in_array('requiresPasswordChange', $empleadoColumns, true)) {
            $payload['requiresPasswordChange'] = false;
        }

        if (in_array('debeCambiarPassword', $empleadoColumns, true)) {
            $payload['debeCambiarPassword'] = false;
        }

        $this->applyRole($payload, $empleadoColumns, $user['rol']);

        if (in_array('created_at', $empleadoColumns, true)) {
            $payload['created_at'] = now();
        }

        if (in_array('updated_at', $empleadoColumns, true)) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    private function applyRole(array &$payload, array $empleadoColumns, string $role): void
    {
        $roleTextColumn = $this->firstExistingColumn($empleadoColumns, [
            'rol',
            'role',
            'tipo',
            'tipoEmpleado',
            'tipo_empleado',
        ]);

        if ($roleTextColumn) {
            $payload[$roleTextColumn] = $role;
            return;
        }

        $roleIdColumn = $this->firstExistingColumn($empleadoColumns, [
            'idTipoEmpleado',
            'id_tipo_empleado',
            'tipoEmpleado_id',
            'tipo_empleado_id',
            'idRol',
            'id_rol',
            'rol_id',
            'role_id',
        ]);

        if ($roleIdColumn) {
            $payload[$roleIdColumn] = $this->findOrCreateTipoEmpleadoId($role);
        }
    }

    private function findOrCreateTipoEmpleadoId(string $role): int
    {
        $tipoModel = new TipoEmpleado();
        $tipoTable = $tipoModel->getTable();
        $tipoPrimaryKey = $tipoModel->getKeyName();

        if (!Schema::hasTable($tipoTable)) {
            throw new \RuntimeException("No existe la tabla {$tipoTable}. Revisa el modelo App\\Models\\TipoEmpleado.");
        }

        $tipoColumns = Schema::getColumnListing($tipoTable);

        $nameColumn = $this->firstExistingColumn($tipoColumns, [
            'nombre',
            'tipo',
            'descripcion',
            'rol',
            'clave',
            'slug',
        ]);

        if (!$nameColumn) {
            throw new \RuntimeException(
                "No se encontró columna de nombre/tipo para roles en {$tipoTable}. " .
                'Columnas detectadas: ' . implode(', ', $tipoColumns)
            );
        }

        $roleAliases = match ($role) {
            'admin' => ['admin', 'administrador', 'Administrador', 'ADMIN'],
            'recepcionista' => ['recepcionista', 'Recepcionista', 'RECEPCIONISTA'],
            'dentista' => ['dentista', 'Dentista', 'DENTISTA'],
            default => [$role],
        };

        $existing = DB::table($tipoTable)
            ->whereIn($nameColumn, $roleAliases)
            ->first();

        if ($existing) {
            return (int) $existing->{$tipoPrimaryKey};
        }

        /*
         * Se usa factory para cubrir columnas obligatorias desconocidas y luego se actualiza.
         */
        $tipoEmpleado = TipoEmpleado::factory()->create();

        $payload = [
            $nameColumn => $role,
        ];

        if (in_array('estado', $tipoColumns, true)) {
            $payload['estado'] = true;
        }

        if (in_array('activo', $tipoColumns, true)) {
            $payload['activo'] = true;
        }

        if (in_array('created_at', $tipoColumns, true)) {
            $payload['created_at'] = now();
        }

        if (in_array('updated_at', $tipoColumns, true)) {
            $payload['updated_at'] = now();
        }

        DB::table($tipoTable)
            ->where($tipoPrimaryKey, $tipoEmpleado->{$tipoPrimaryKey})
            ->update($payload);

        return (int) $tipoEmpleado->{$tipoPrimaryKey};
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function putIfColumnExists(array &$payload, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $payload[$column] = $value;
        }
    }
}
