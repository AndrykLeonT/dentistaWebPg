# Modelo de Dominio

## Diagrama de entidades

```
TipoEmpleado ──────────► Empleado ◄──────────── Persona
                              │                     ▲
                              │                     │
                              ▼                     │
                            Pago ◄──── Corte        │
                              ▲                     │
                              │                     │
ClaseServicio ──► Servicio ──► Cita ────────────────┘
                               │
                               ▼
                            Receta
```

---

## Entidades

### Persona
Registro base de un paciente (o cualquier persona en el sistema).

| Columna | Tipo | Notas |
|---|---|---|
| `idPersona` | PK int | PK no estándar — declarar `$primaryKey` |
| `nombre` | string | |
| `apellidoP` | string | Apellido paterno |
| `apellidoM` | string | Apellido materno |
| `celular` | string | |
| `correoElectronico` | string | |
| `estado` | boolean | 1=activo, 0=inactivo |

**Reglas:** Un paciente puede tener múltiples Citas. No se elimina físicamente — se desactiva con `estado=0`.

---

### Empleado
Extiende Persona. Representa al staff de la clínica.

| Columna | Tipo | Notas |
|---|---|---|
| `idEmpleado` | PK int | PK no estándar |
| `idPersona` | FK int | → Persona |
| `idTipoEmpleado` | FK int | → TipoEmpleado |
| `usuario` | string | Para login |
| `rfc` | string | |
| `contraseña` | string | Hash bcrypt |
| `palabraClave` | string | Para recuperación de cuenta |
| `cambioContraseña` | boolean | Forzar cambio en próximo login |
| `estado` | boolean | 1=activo |

**Reglas:** Solo Empleados pueden hacer login. Un Empleado tiene exactamente un TipoEmpleado.

---

### TipoEmpleado
Catálogo de tipos de empleado.

| Columna | Tipo | Notas |
|---|---|---|
| `idTipoEmpleado` | PK int | |
| `nombre` | string | Ej: "Dentista", "Recepcionista", "Administrador" |
| `estado` | boolean | |

**Valores esperados en seed:** Administrador, Dentista, Recepcionista.

---

### ClaseServicio
Categoría de servicios dentales.

| Columna | Tipo | Notas |
|---|---|---|
| `idClaseServicio` | PK int | |
| `nombre` | string | Ej: "Ortodoncia", "Limpieza" |
| `estado` | boolean | |

---

### Servicio
Servicio dental específico ofrecido por la clínica.

| Columna | Tipo | Notas |
|---|---|---|
| `idServicio` | PK int | |
| `idClaseServicio` | FK int | → ClaseServicio |
| `nombre` | string | |
| `costo` | decimal | |
| `duracion` | time | Duración estimada |
| `estado` | boolean | |

---

### Cita
Turno/cita que une a un Paciente con un Servicio.

| Columna | Tipo | Notas |
|---|---|---|
| `idCita` | PK int | |
| `idPersona` | FK int | → Persona (paciente) |
| `idServicio` | FK int | → Servicio |
| `fechaRegistro` | date | Fecha en que se agendó la cita |
| `fechaProgramada` | date | Fecha en que se realizará |
| `hora` | time | |
| `duracion` | time | Duración real de la cita (puede diferir de `duracion` del Servicio) |
| `motivo` | text | Motivo de la consulta |
| `estado` | boolean | |

**Reglas:** Una Cita puede tener cero o una Receta. La cita pertenece a un paciente y un servicio.

---

### Receta
Prescripción médica ligada a una Cita.

| Columna | Tipo | Notas |
|---|---|---|
| `idReceta` | PK int | |
| `idCita` | FK int | → Cita |
| `indicaciones` | text | Instrucciones / medicamentos |
| `estado` | boolean | |

---

### Corte
Corte de caja — período de cierre de turno con totales.

| Columna | Tipo | Notas |
|---|---|---|
| `idCorte` | PK int | |
| `fechaInicio` | datetime | |
| `fechaFin` | datetime | nullable — null mientras el corte esté abierto |
| `fDeCaja` | decimal | Fondo de caja inicial |
| `tEfectivo` | decimal | Total efectivo del período |
| `tTarjeta` | decimal | Total tarjeta del período |
| `correcto` | boolean | nullable — indica si el corte cuadró correctamente al cerrar |
| `estado` | boolean | Soft-delete estándar — 1=activo, 0=inactivo |

**Nota:** `correcto` y `estado` son independientes. `estado` controla visibilidad/eliminación lógica. `correcto` es un resultado del proceso de cierre de caja.

---

### Pago
Registro de pago vinculado a un paciente, empleado y corte.

| Columna | Tipo | Notas |
|---|---|---|
| `idPago` | PK int | |
| `idPersona` | FK int | → Persona (quién pagó) |
| `idEmpleado` | FK int | → Empleado (quién cobró) |
| `idCorte` | FK int | nullable → Corte activo |
| `fechaRegistro` | date | Fecha en que se registró el pago |
| `total` | decimal | Monto total del pago (efectivo + tarjeta) |
| `pagado` | boolean | 0=pendiente, 1=liquidado |
| `efectivo` | decimal | Porción pagada en efectivo |
| `tarjeta` | decimal | Porción pagada con tarjeta |
| `estado` | boolean | Soft-delete estándar |

---

## Patrón `estado` (soft-enable)

**Ninguna entidad se elimina físicamente.** En lugar de `DELETE`, se hace:
```php
$model->estado = 0;
$model->save();
```

Todos los listados deben filtrar por `estado = 1` por defecto.
Agregar scope global o local en cada modelo:
```php
public function scopeActivos($query) {
    return $query->where('estado', 1);
}
```

---

## Relaciones Eloquent pendientes de implementar

```php
// Persona
hasMany(Cita::class, 'idPersona')
hasMany(Pago::class, 'idPersona')
hasOne(Empleado::class, 'idPersona')

// Empleado
belongsTo(Persona::class, 'idPersona')
belongsTo(TipoEmpleado::class, 'idTipoEmpleado')
hasMany(Pago::class, 'idEmpleado')

// Cita
belongsTo(Persona::class, 'idPersona')
belongsTo(Servicio::class, 'idServicio')
hasOne(Receta::class, 'idCita')

// Servicio
belongsTo(ClaseServicio::class, 'idClaseServicio')
hasMany(Cita::class, 'idServicio')

// Receta
belongsTo(Cita::class, 'idCita')

// Pago
belongsTo(Persona::class, 'idPersona')
belongsTo(Empleado::class, 'idEmpleado')
belongsTo(Corte::class, 'idCorte')

// Corte
hasMany(Pago::class, 'idCorte')
```
