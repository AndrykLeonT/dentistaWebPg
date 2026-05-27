<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCitaRequest;
use App\Http\Requests\UpdateCitaRequest;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use Illuminate\Http\Request;

class CitaController extends Controller
{
    public function index(Request $request)
    {
        $query = Cita::activos()->with('persona', 'servicio', 'empleado.persona');

        if ($fecha = $request->query('fecha')) {
            $query->whereDate('fechaProgramada', $fecha);
        }

        if ($pacienteId = $request->query('paciente_id')) {
            $query->where('idPersona', $pacienteId);
        }

        if ($servicioId = $request->query('servicio_id')) {
            $query->where('idServicio', $servicioId);
        }

        return CitaResource::collection($query->get());
    }

    public function store(StoreCitaRequest $request)
    {
        $data = $request->validated();

        if (($data['motivo'] ?? null) === null) {
            $data['motivo'] = '';
        }

        $cita = Cita::create($data + [
            'fechaRegistro' => now(),
            'estado'        => true,
        ]);

        return new CitaResource($cita->load('persona', 'servicio', 'empleado.persona'));
    }

    public function show(Cita $cita)
    {
        return new CitaResource($cita->load('persona', 'servicio', 'empleado.persona', 'receta'));
    }

    public function update(UpdateCitaRequest $request, Cita $cita)
    {
        $data = $request->validated();

        if (array_key_exists('motivo', $data) && $data['motivo'] === null) {
            $data['motivo'] = '';
        }

        $cita->update($data);

        return new CitaResource($cita->load('persona', 'servicio', 'empleado.persona'));
    }

    public function destroy(Cita $cita)
    {
        $cita->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
