<?php

namespace App\Http\Controllers;

use App\Models\Persona;

class HistorialPacienteController extends Controller
{
    public function citas(Persona $persona)
    {
        $this->asegurarActiva($persona);

        $citas = $persona->citas()
            ->with('servicio', 'empleado.persona')
            ->orderByDesc('fechaProgramada')
            ->orderByDesc('hora')
            ->get()
            ->map(fn ($cita) => [
                'id' => $cita->idCita,
                'fecha' => $cita->fechaProgramada?->format('Y-m-d'),
                'hora' => $this->horaCorta($cita->hora),
                'estado' => $cita->estado ? 'activa' : 'inactiva',
                'servicio' => $cita->servicio?->nombre,
                'dentista' => $this->nombreCompleto($cita->empleado?->persona),
                'observaciones' => $cita->motivo,
            ]);

        return response()->json($citas);
    }

    public function pagos(Persona $persona)
    {
        $this->asegurarActiva($persona);

        $pagos = $persona->pagos()
            ->activos()
            ->with('comprobante')
            ->orderByDesc('fechaRegistro')
            ->orderByDesc('idPago')
            ->get()
            ->map(fn ($pago) => [
                'id' => $pago->idPago,
                'fecha' => $pago->fechaRegistro?->format('Y-m-d'),
                'total' => $pago->total,
                'efectivo' => $pago->efectivo,
                'tarjeta' => $pago->tarjeta,
                'folioComprobante' => $pago->comprobante?->estado ? $pago->comprobante->folio : null,
                'estado' => $pago->pagado ? 'pagado' : 'pendiente',
            ]);

        return response()->json($pagos);
    }

    private function asegurarActiva(Persona $persona): void
    {
        abort_unless($persona->estado, 404);
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
