<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Corte extends Model
{
    use HasFactory;

    protected $primaryKey = 'idCorte';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'fechaInicio',
        'fechaFin',
        'fDeCaja',
        'tEfectivo',
        'tTarjeta',
        'correcto',
        'estado',
    ];

    protected $casts = [
        'fechaInicio' => 'datetime',
        'fechaFin'    => 'datetime',
        'fDeCaja'     => 'decimal:2',
        'tEfectivo'   => 'decimal:2',
        'tTarjeta'    => 'decimal:2',
        'correcto'    => 'boolean',
        'estado'      => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'idCorte', 'idCorte');
    }
}
