<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\ConsumoInventarioCita;
use App\Models\ConsumoServicio;
use App\Models\Empleado;
use App\Models\ProductoInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConsumoInventarioCitaService
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    public function consumir(Cita $cita, Empleado $empleado): ConsumoInventarioCita
    {
        abort_unless($cita->estado, 404);

        return DB::transaction(function () use ($cita, $empleado) {
            $cita = Cita::activos()
                ->with('servicio')
                ->lockForUpdate()
                ->findOrFail($cita->idCita);

            if (ConsumoInventarioCita::where('idCita', $cita->idCita)->where('estado', true)->exists()) {
                throw new ConflictHttpException('El inventario de esta cita ya fue consumido.');
            }

            $reglas = ConsumoServicio::activos()
                ->with('producto')
                ->where('idServicio', $cita->idServicio)
                ->get();

            if ($reglas->isEmpty()) {
                throw ValidationException::withMessages([
                    'idServicio' => 'El servicio de la cita no tiene reglas activas de consumo de inventario.',
                ]);
            }

            foreach ($reglas as $regla) {
                $producto = ProductoInventario::lockForUpdate()->find($regla->idProductoInventario);

                if (! $producto || ! $producto->estado) {
                    throw ValidationException::withMessages([
                        'idProductoInventario' => 'Una regla de consumo usa un producto inactivo o inexistente.',
                    ]);
                }

                $stockActual = $this->unidades($producto->stockActual, 'stockActual');
                $cantidad = $this->unidades($regla->cantidad, 'cantidad');

                if ($stockActual < $cantidad) {
                    throw ValidationException::withMessages([
                        'cantidad' => "Stock insuficiente para {$producto->nombre}.",
                    ]);
                }

            }

            $consumo = ConsumoInventarioCita::create([
                'idCita' => $cita->idCita,
                'idEmpleado' => $empleado->idEmpleado,
                'fechaConsumo' => now(),
                'estado' => true,
            ]);

            foreach ($reglas as $regla) {
                $movimiento = $this->inventario->registrarMovimiento([
                    'idProductoInventario' => $regla->idProductoInventario,
                    'tipoMovimiento' => 'salida',
                    'cantidad' => $regla->cantidad,
                    'motivo' => "Consumo automatico por cita #{$cita->idCita}",
                ], $empleado);

                $consumo->movimientos()->attach($movimiento->idMovimientoInventario);
            }

            return $consumo->load('movimientos.producto', 'movimientos.empleado');
        });
    }

    private function unidades(mixed $cantidad, string $campo): int
    {
        $valor = trim((string) $cantidad);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $valor, $partes)) {
            throw ValidationException::withMessages([
                $campo => 'La cantidad admite hasta dos decimales y no puede ser negativa.',
            ]);
        }

        return ((int) $partes[1] * 100)
            + (int) str_pad($partes[2] ?? '', 2, '0');
    }
}
