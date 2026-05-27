<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMovimientoInventarioRequest;
use App\Http\Resources\MovimientoInventarioResource;
use App\Models\MovimientoInventario;
use App\Services\InventarioService;
use Illuminate\Http\Request;

class MovimientoInventarioController extends Controller
{
    public function index(Request $request)
    {
        $query = MovimientoInventario::with('producto', 'empleado');

        if ($request->filled('idProductoInventario')) {
            $query->where('idProductoInventario', $request->integer('idProductoInventario'));
        }

        return MovimientoInventarioResource::collection(
            $query->orderByDesc('fechaMovimiento')->get()
        );
    }

    public function store(StoreMovimientoInventarioRequest $request, InventarioService $inventario)
    {
        $movimiento = $inventario->registrarMovimiento($request->validated(), $request->user());

        return new MovimientoInventarioResource($movimiento);
    }
}
