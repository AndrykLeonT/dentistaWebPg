<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    use HasFactory;

    protected $primaryKey = 'idReceta';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idCita',
        'indicaciones',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'idCita', 'idCita');
    }
}
