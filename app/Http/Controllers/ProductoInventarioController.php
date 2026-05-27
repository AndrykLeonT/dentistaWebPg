<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoInventarioRequest;
use App\Http\Requests\UpdateProductoInventarioRequest;
use App\Http\Resources\ProductoInventarioResource;
use App\Models\ProductoInventario;
use App\Services\InventarioService;

class ProductoInventarioController extends Controller
{
    public function index()
    {
        return ProductoInventarioResource::collection(ProductoInventario::activos()->get());
    }

    public function store(StoreProductoInventarioRequest $request, InventarioService $inventario)
    {
        $producto = $inventario->crearProducto($request->validated(), $request->user());

        return new ProductoInventarioResource($producto);
    }

    public function show(ProductoInventario $producto)
    {
        $this->asegurarActivo($producto);

        return new ProductoInventarioResource($producto);
    }

    public function update(
        UpdateProductoInventarioRequest $request,
        ProductoInventario $producto,
        InventarioService $inventario
    ) {
        $this->asegurarActivo($producto);
        $producto = $inventario->actualizarProducto($producto, $request->validated());

        return new ProductoInventarioResource($producto);
    }

    public function destroy(ProductoInventario $producto, InventarioService $inventario)
    {
        $this->asegurarActivo($producto);
        $inventario->desactivarProducto($producto);

        return response()->json(null, 204);
    }

    private function asegurarActivo(ProductoInventario $producto): void
    {
        abort_unless($producto->estado, 404);
    }
}
