<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';
    protected $primaryKey = 'idMovimientoInventario';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idProductoInventario',
        'idEmpleado',
        'tipoMovimiento',
        'cantidad',
        'stockAnterior',
        'stockNuevo',
        'motivo',
        'fechaMovimiento',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'stockAnterior' => 'decimal:2',
        'stockNuevo' => 'decimal:2',
        'fechaMovimiento' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(ProductoInventario::class, 'idProductoInventario', 'idProductoInventario');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'idEmpleado', 'idEmpleado');
    }
}
