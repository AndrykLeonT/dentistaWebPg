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
        $query = Cita::activos()->with('persona', 'servicio');

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
        $cita = Cita::create($request->validated() + [
            'fechaRegistro' => now(),
            'estado'        => true,
        ]);

        return new CitaResource($cita->load('persona', 'servicio'));
    }

    public function show(Cita $cita)
    {
        return new CitaResource($cita->load('persona', 'servicio', 'receta'));
    }

    public function update(UpdateCitaRequest $request, Cita $cita)
    {
        $cita->update($request->validated());

        return new CitaResource($cita->load('persona', 'servicio'));
    }

    public function destroy(Cita $cita)
    {
        $cita->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
