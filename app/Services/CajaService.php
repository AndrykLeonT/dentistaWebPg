<?php

namespace App\Services;

use App\Models\Corte;
use App\Models\Empleado;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaService
{
    public function pagoEstaLiquidado(mixed $total, mixed $efectivo, mixed $tarjeta): bool
    {
        $totalCentavos = $this->centavos($total);
        $efectivoCentavos = $this->centavos($efectivo);
        $tarjetaCentavos = $this->centavos($tarjeta);

        return $totalCentavos !== null
            && $efectivoCentavos !== null
            && $tarjetaCentavos !== null
            && $totalCentavos > 0
            && $efectivoCentavos >= 0
            && $tarjetaCentavos >= 0
            && ($efectivoCentavos + $tarjetaCentavos) === $totalCentavos;
    }

    public function registrarPago(array $data, Empleado $empleado): ?Pago
    {
        return DB::transaction(function () use ($data, $empleado) {
            $corte = Corte::activos()
                ->whereNull('fechaFin')
                ->lockForUpdate()
                ->first();

            if (! $corte) {
                return null;
            }

            return Pago::create($data + [
                'idEmpleado' => $empleado->idEmpleado,
                'idCorte' => $corte->idCorte,
                'fechaRegistro' => now(),
                'pagado' => true,
                'estado' => true,
            ]);
        });
    }

    public function actualizarPago(Pago $pago, array $data): Pago
    {
        return DB::transaction(function () use ($pago, $data) {
            $pagoActual = Pago::with('corte')->lockForUpdate()->findOrFail($pago->idPago);

            $this->validarCorteAbiertoParaPago($pagoActual);
            $pagoActual->update($data + ['pagado' => true]);

            return $pagoActual;
        });
    }

    public function desactivarPago(Pago $pago): void
    {
        DB::transaction(function () use ($pago) {
            $pagoActual = Pago::with('corte')->lockForUpdate()->findOrFail($pago->idPago);

            $this->validarCorteAbiertoParaPago($pagoActual);
            $pagoActual->update(['estado' => false]);
        });
    }

    public function abrirCorte(array $data): ?Corte
    {
        return DB::transaction(function () use ($data) {
            $hayAbierto = Corte::activos()
                ->whereNull('fechaFin')
                ->lockForUpdate()
                ->exists();

            if ($hayAbierto) {
                return null;
            }

            return Corte::create($data + [
                'fechaInicio' => now(),
                'tEfectivo' => 0,
                'tTarjeta' => 0,
                'estado' => true,
            ]);
        });
    }

    public function actualizarCorte(Corte $corte, array $data): Corte
    {
        return DB::transaction(function () use ($corte, $data) {
            $corteActual = Corte::lockForUpdate()->findOrFail($corte->idCorte);

            if ($corteActual->fechaFin !== null) {
                throw ValidationException::withMessages([
                    'corte' => 'Un corte cerrado no puede modificarse.',
                ]);
            }

            if (array_key_exists('fechaFin', $data)) {
                $totales = $corteActual->pagos()
                    ->where('estado', true)
                    ->selectRaw('COALESCE(SUM(efectivo), 0) as totalEfectivo, COALESCE(SUM(tarjeta), 0) as totalTarjeta')
                    ->first();

                $data['tEfectivo'] = $totales->totalEfectivo;
                $data['tTarjeta'] = $totales->totalTarjeta;
            }

            $corteActual->update($data);

            return $corteActual;
        });
    }

    public function desactivarCorte(Corte $corte): void
    {
        DB::transaction(function () use ($corte) {
            $corteActual = Corte::lockForUpdate()->findOrFail($corte->idCorte);

            if ($corteActual->fechaFin !== null) {
                throw ValidationException::withMessages([
                    'corte' => 'Un corte cerrado no puede modificarse.',
                ]);
            }

            $corteActual->update(['estado' => false]);
        });
    }

    private function validarCorteAbiertoParaPago(Pago $pago): void
    {
        if (! $pago->corte || $pago->corte->fechaFin !== null) {
            throw ValidationException::withMessages([
                'pago' => 'No se puede modificar un pago asociado a un corte cerrado.',
            ]);
        }
    }

    private function centavos(mixed $monto): ?int
    {
        $valor = trim((string) $monto);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $valor, $partes)) {
            return null;
        }

        $centavos = ((int) $partes[2] * 100)
            + (int) str_pad($partes[3] ?? '', 2, '0');

        return ($partes[1] ?? '') === '-' ? -$centavos : $centavos;
    }
}
