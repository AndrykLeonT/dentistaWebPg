<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model
{
    use HasFactory;

    protected $primaryKey = 'idComprobante';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idPago',
        'folio',
        'fechaEmision',
        'total',
        'efectivo',
        'tarjeta',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fechaEmision' => 'datetime',
        'total' => 'decimal:2',
        'efectivo' => 'decimal:2',
        'tarjeta' => 'decimal:2',
        'estado' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'idPago', 'idPago');
    }
}
