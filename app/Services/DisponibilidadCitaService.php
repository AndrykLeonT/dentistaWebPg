<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Empleado;
use App\Models\Servicio;
use Carbon\Carbon;

class DisponibilidadCitaService
{
    public function esDentistaActivo(int $idEmpleado): bool
    {
        $empleado = Empleado::with('tipoEmpleado')->find($idEmpleado);

        if (! $empleado || ! $empleado->estado) {
            return false;
        }

        return strtolower(trim($empleado->tipoEmpleado?->nombre ?? '')) === 'dentista';
    }

    public function tieneTraslape(
        int $idEmpleado,
        string $fechaProgramada,
        string $hora,
        int $idServicio,
        ?int $citaExcluidaId = null
    ): bool {
        $servicio = Servicio::find($idServicio);

        if (! $servicio) {
            return false;
        }

        $inicio = $this->crearFechaHora($fechaProgramada, $hora);
        $fin = $inicio->copy()->addSeconds($this->duracionEnSegundos($servicio->duracion));

        $query = Cita::activos()
            ->whereDate('fechaProgramada', $fechaProgramada)
            ->where('idEmpleado', $idEmpleado)
            ->with('servicio:idServicio,duracion');

        if ($citaExcluidaId !== null) {
            $query->where('idCita', '!=', $citaExcluidaId);
        }

        return $query->get()->contains(function (Cita $cita) use ($fechaProgramada, $inicio, $fin) {
            if (! $cita->servicio) {
                return false;
            }

            $inicioExistente = $this->crearFechaHora($fechaProgramada, $cita->hora);
            $finExistente = $inicioExistente->copy()
                ->addSeconds($this->duracionEnSegundos($cita->servicio->duracion));

            return $inicio->lt($finExistente) && $fin->gt($inicioExistente);
        });
    }

    private function crearFechaHora(string $fechaProgramada, string $hora): Carbon
    {
        return Carbon::parse("{$fechaProgramada} {$hora}");
    }

    private function duracionEnSegundos(string $duracion): int
    {
        [$horas, $minutos, $segundos] = array_map('intval', explode(':', $duracion));

        return ($horas * 3600) + ($minutos * 60) + $segundos;
    }
}
