<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;

    protected $primaryKey = 'idPersona';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'apellidoP',
        'apellidoM',
        'celular',
        'correoElectronico',
        'fechaRegistro',
        'estado',
    ];

    protected $casts = [
        'fechaRegistro' => 'date',
        'estado'        => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function citas()
    {
        return $this->hasMany(Cita::class, 'idPersona', 'idPersona');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'idPersona', 'idPersona');
    }

    public function empleado()
    {
        return $this->hasOne(Empleado::class, 'idPersona', 'idPersona');
    }
}
