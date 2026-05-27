<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\ProductoInventario;

class DashboardController extends Controller
{
    public function resumen()
    {
        $hoy = now()->toDateString();

        $citasProximas = Cita::activos()
            ->with('persona', 'servicio', 'empleado.persona')
            ->whereDate('fechaProgramada', '>=', $hoy)
            ->orderBy('fechaProgramada')
            ->orderBy('hora')
            ->limit(5)
            ->get()
            ->map(fn ($cita) => [
                'id' => $cita->idCita,
                'fecha' => $cita->fechaProgramada?->format('Y-m-d'),
                'hora' => $this->horaCorta($cita->hora),
                'paciente' => $this->nombreCompleto($cita->persona),
                'servicio' => $cita->servicio?->nombre,
                'dentista' => $this->nombreCompleto($cita->empleado?->persona),
            ]);

        $alertasInventario = ProductoInventario::activos()
            ->whereColumn('stockActual', '<=', 'stockMinimo')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($producto) => [
                'id' => $producto->idProductoInventario,
                'nombre' => $producto->nombre,
                'stockActual' => $producto->stockActual,
                'stockMinimo' => $producto->stockMinimo,
                'unidadMedida' => $producto->unidadMedida,
            ]);

        return response()->json([
            'pacientesActivos' => Persona::activos()->whereDoesntHave('empleado')->count(),
            'citasHoy' => Cita::activos()->whereDate('fechaProgramada', $hoy)->count(),
            'ingresosHoy' => (float) Pago::activos()->whereDate('fechaRegistro', $hoy)->sum('total'),
            'productosBajoStock' => ProductoInventario::activos()
                ->whereColumn('stockActual', '<=', 'stockMinimo')
                ->count(),
            'citasProximas' => $citasProximas,
            'alertasInventario' => $alertasInventario,
        ]);
    }

    private function nombreCompleto(?Persona $persona): ?string
    {
        if (! $persona) {
            return null;
        }

        return trim("{$persona->nombre} {$persona->apellidoP} {$persona->apellidoM}");
    }

    private function horaCorta(?string $hora): ?string
    {
        return $hora ? substr($hora, 0, 5) : null;
    }
}
