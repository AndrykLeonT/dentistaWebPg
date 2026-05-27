<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoInventario extends Model
{
    use HasFactory;

    protected $table = 'productos_inventario';
    protected $primaryKey = 'idProductoInventario';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'descripcion',
        'unidadMedida',
        'stockActual',
        'stockMinimo',
        'costoUnitario',
        'estado',
    ];

    protected $casts = [
        'stockActual' => 'decimal:2',
        'stockMinimo' => 'decimal:2',
        'costoUnitario' => 'decimal:2',
        'estado' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'idProductoInventario', 'idProductoInventario');
    }

    public function consumosServicio()
    {
        return $this->hasMany(ConsumoServicio::class, 'idProductoInventario', 'idProductoInventario');
    }
}
