<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumoServicio extends Model
{
    use HasFactory;

    protected $table = 'consumos_servicio';
    protected $primaryKey = 'idConsumoServicio';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idServicio',
        'idProductoInventario',
        'cantidad',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'estado' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'idServicio', 'idServicio');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoInventario::class, 'idProductoInventario', 'idProductoInventario');
    }
}
