<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    use HasFactory;

    protected $primaryKey = 'idCita';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idPersona',
        'idServicio',
        'idEmpleado',
        'fechaRegistro',
        'fechaProgramada',
        'hora',
        'duracion',
        'motivo',
        'estado',
    ];

    protected $casts = [
        'fechaRegistro'   => 'date',
        'fechaProgramada' => 'date',
        'estado'          => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'idServicio', 'idServicio');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'idEmpleado', 'idEmpleado');
    }

    public function receta()
    {
        return $this->hasOne(Receta::class, 'idCita', 'idCita');
    }

    public function consumoInventario()
    {
        return $this->hasOne(ConsumoInventarioCita::class, 'idCita', 'idCita');
    }
}
