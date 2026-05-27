<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $primaryKey = 'idPago';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idPersona',
        'idEmpleado',
        'idCorte',
        'fechaRegistro',
        'total',
        'pagado',
        'efectivo',
        'tarjeta',
        'estado',
    ];

    protected $casts = [
        'fechaRegistro' => 'date',
        'total'         => 'decimal:2',
        'efectivo'      => 'decimal:2',
        'tarjeta'       => 'decimal:2',
        'pagado'        => 'boolean',
        'estado'        => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'idEmpleado', 'idEmpleado');
    }

    public function corte()
    {
        return $this->belongsTo(Corte::class, 'idCorte', 'idCorte');
    }

    public function comprobante()
    {
        return $this->hasOne(Comprobante::class, 'idPago', 'idPago');
    }
}
