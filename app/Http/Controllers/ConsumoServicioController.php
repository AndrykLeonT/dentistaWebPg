<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsumoServicioRequest;
use App\Http\Requests\UpdateConsumoServicioRequest;
use App\Http\Resources\ConsumoServicioResource;
use App\Models\ConsumoServicio;

class ConsumoServicioController extends Controller
{
    public function index()
    {
        return ConsumoServicioResource::collection(
            ConsumoServicio::activos()
                ->with('servicio', 'producto')
                ->orderBy('idServicio')
                ->orderBy('idProductoInventario')
                ->get()
        );
    }

    public function store(StoreConsumoServicioRequest $request)
    {
        $consumo = ConsumoServicio::create($request->validated() + [
            'estado' => true,
        ]);

        return new ConsumoServicioResource($consumo->load('servicio', 'producto'));
    }

    public function show(ConsumoServicio $consumoServicio)
    {
        $this->asegurarActivo($consumoServicio);

        return new ConsumoServicioResource($consumoServicio->load('servicio', 'producto'));
    }

    public function update(UpdateConsumoServicioRequest $request, ConsumoServicio $consumoServicio)
    {
        $this->asegurarActivo($consumoServicio);

        $consumoServicio->update($request->validated());

        return new ConsumoServicioResource($consumoServicio->load('servicio', 'producto'));
    }

    public function destroy(ConsumoServicio $consumoServicio)
    {
        $this->asegurarActivo($consumoServicio);

        $consumoServicio->update(['estado' => false]);

        return response()->json(null, 204);
    }

    private function asegurarActivo(ConsumoServicio $consumoServicio): void
    {
        abort_unless($consumoServicio->estado, 404);
    }
}
