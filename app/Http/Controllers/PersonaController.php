<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use App\Http\Resources\PersonaResource;
use App\Models\Persona;
use Illuminate\Http\Request;

class PersonaController extends Controller
{
    public function index(Request $request)
    {
        $query = Persona::activos();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('apellidoP', 'like', "%{$search}%")
                  ->orWhere('apellidoM', 'like', "%{$search}%");
            });
        }

        return PersonaResource::collection($query->get());
    }

    public function store(StorePersonaRequest $request)
    {
        $persona = Persona::create($request->validated() + [
            'fechaRegistro' => now()->toDateString(),
            'estado'        => true,
        ]);

        return new PersonaResource($persona);
    }

    public function show(Persona $persona)
    {
        return new PersonaResource($persona->load('citas', 'pagos'));
    }

    public function update(UpdatePersonaRequest $request, Persona $persona)
    {
        $persona->update($request->validated());

        return new PersonaResource($persona);
    }

    public function destroy(Persona $persona)
    {
        $persona->update(['estado' => false]);

        return response()->json(null, 204);
    }
}
