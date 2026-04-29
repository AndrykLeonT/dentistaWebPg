<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaseServicio extends Model
{
    use HasFactory;

    protected $primaryKey = 'idClaseServicio';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'idClaseServicio', 'idClaseServicio');
    }
}
