<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\MovimientoInventario;
use App\Models\ProductoInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    public function crearProducto(array $data, Empleado $empleado): ProductoInventario
    {
        return DB::transaction(function () use ($data, $empleado) {
            $this->asegurarNombreDisponible($data['nombre']);

            $stockInicial = $this->unidades($data['stockInicial'], 'stockInicial');
            $producto = ProductoInventario::create([
                'nombre' => trim($data['nombre']),
                'descripcion' => $data['descripcion'] ?? null,
                'unidadMedida' => $data['unidadMedida'],
                'stockActual' => $this->decimal($stockInicial),
                'stockMinimo' => $data['stockMinimo'] ?? 0,
                'costoUnitario' => $data['costoUnitario'] ?? null,
                'estado' => true,
            ]);

            MovimientoInventario::create([
                'idProductoInventario' => $producto->idProductoInventario,
                'idEmpleado' => $empleado->idEmpleado,
                'tipoMovimiento' => 'ajuste',
                'cantidad' => $this->decimal($stockInicial),
                'stockAnterior' => '0.00',
                'stockNuevo' => $this->decimal($stockInicial),
                'motivo' => 'Stock inicial',
                'fechaMovimiento' => now(),
            ]);

            return $producto;
        });
    }

    public function actualizarProducto(ProductoInventario $producto, array $data): ProductoInventario
    {
        return DB::transaction(function () use ($producto, $data) {
            $actual = ProductoInventario::lockForUpdate()->findOrFail($producto->idProductoInventario);
            $this->asegurarActivo($actual);

            if (array_key_exists('nombre', $data)) {
                $this->asegurarNombreDisponible($data['nombre'], $actual->idProductoInventario);
                $data['nombre'] = trim($data['nombre']);
            }

            $actual->update($data);

            return $actual;
        });
    }

    public function desactivarProducto(ProductoInventario $producto): void
    {
        DB::transaction(function () use ($producto) {
            $actual = ProductoInventario::lockForUpdate()->findOrFail($producto->idProductoInventario);
            $this->asegurarActivo($actual);
            $actual->update(['estado' => false]);
        });
    }

    public function registrarMovimiento(array $data, Empleado $empleado): MovimientoInventario
    {
        return DB::transaction(function () use ($data, $empleado) {
            $producto = ProductoInventario::activos()
                ->lockForUpdate()
                ->findOrFail($data['idProductoInventario']);
            $stockAnterior = $this->unidades($producto->stockActual, 'stockActual');
            $cantidad = $this->unidades($data['cantidad'], 'cantidad');
            $tipo = $data['tipoMovimiento'];

            if (in_array($tipo, ['entrada', 'salida'], true) && $cantidad <= 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La cantidad debe ser mayor que cero para entradas y salidas.',
                ]);
            }

            $stockNuevo = match ($tipo) {
                'entrada' => $stockAnterior + $cantidad,
                'salida' => $stockAnterior - $cantidad,
                'ajuste' => $cantidad,
            };

            if ($stockNuevo < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La salida no puede dejar el inventario con stock negativo.',
                ]);
            }

            $producto->update(['stockActual' => $this->decimal($stockNuevo)]);

            return MovimientoInventario::create([
                'idProductoInventario' => $producto->idProductoInventario,
                'idEmpleado' => $empleado->idEmpleado,
                'tipoMovimiento' => $tipo,
                'cantidad' => $this->decimal($cantidad),
                'stockAnterior' => $this->decimal($stockAnterior),
                'stockNuevo' => $this->decimal($stockNuevo),
                'motivo' => $data['motivo'] ?? null,
                'fechaMovimiento' => now(),
            ])->load('producto', 'empleado');
        });
    }

    private function asegurarNombreDisponible(string $nombre, ?int $exceptoId = null): void
    {
        $query = ProductoInventario::activos()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($nombre))]);

        if ($exceptoId !== null) {
            $query->where('idProductoInventario', '!=', $exceptoId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un producto activo con ese nombre.',
            ]);
        }
    }

    private function asegurarActivo(ProductoInventario $producto): void
    {
        abort_unless($producto->estado, 404);
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

    private function decimal(int $unidades): string
    {
        return number_format($unidades / 100, 2, '.', '');
    }
}
