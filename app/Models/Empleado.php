<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Empleado extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $primaryKey = 'idEmpleado';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idPersona',
        'idTipoEmpleado',
        'usuario',
        'rfc',
        'contraseña',
        'palabraClave',
        'cambioContraseña',
        'estado',
    ];

    protected $hidden = [
        'contraseña',
        'palabraClave',
        'remember_token',
    ];

    protected $casts = [
        'cambioContraseña' => 'boolean',
        'estado'           => 'boolean',
    ];

    // La tabla no tiene columna remember_token — desactivado para auth API con Sanctum
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    // Laravel espera campo "password" por convención — apuntamos al campo real
    public function getAuthPassword(): string
    {
        return $this->contraseña;
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function tipoEmpleado()
    {
        return $this->belongsTo(TipoEmpleado::class, 'idTipoEmpleado', 'idTipoEmpleado');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'idEmpleado', 'idEmpleado');
    }
}
