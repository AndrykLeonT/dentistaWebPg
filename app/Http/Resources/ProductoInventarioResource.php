<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoInventarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->idProductoInventario,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'unidadMedida' => $this->unidadMedida,
            'stockActual' => $this->stockActual,
            'stockMinimo' => $this->stockMinimo,
            'costoUnitario' => $this->costoUnitario,
            'bajoStock' => (float) $this->stockActual <= (float) $this->stockMinimo,
            'estado' => $this->estado,
        ];
    }
}
