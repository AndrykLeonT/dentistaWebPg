<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumoInventarioCita extends Model
{
    protected $table = 'consumos_inventario_cita';
    protected $primaryKey = 'idConsumoInventarioCita';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idCita',
        'idEmpleado',
        'fechaConsumo',
        'estado',
    ];

    protected $casts = [
        'fechaConsumo' => 'datetime',
        'estado' => 'boolean',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'idCita', 'idCita');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'idEmpleado', 'idEmpleado');
    }

    public function movimientos()
    {
        return $this->belongsToMany(
            MovimientoInventario::class,
            'consumo_inventario_cita_movimientos',
            'idConsumoInventarioCita',
            'idMovimientoInventario'
        )->withTimestamps();
    }
}
