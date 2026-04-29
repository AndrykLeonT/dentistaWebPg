<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $primaryKey = 'idServicio';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idClaseServicio',
        'nombre',
        'descripcion',
        'costo',
        'duracion',
        'estado',
    ];

    protected $casts = [
        'costo'  => 'decimal:2',
        'estado' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function claseServicio()
    {
        return $this->belongsTo(ClaseServicio::class, 'idClaseServicio', 'idClaseServicio');
    }

    public function citas()
    {
        return $this->hasMany(Cita::class, 'idServicio', 'idServicio');
    }
}
