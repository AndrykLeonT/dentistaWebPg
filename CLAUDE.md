# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sistema de gestión para consultorio dental built with **Laravel 10** (PHP 8.1+), MySQL, and Vite. The app manages the full clinic workflow: patients, employees, appointments, prescriptions, services, and cash register cuts.

## Common Commands

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Run migrations fresh with seed data
php artisan migrate:fresh --seed

# Run migrations only
php artisan migrate

# Start Vite dev server
npm run dev

# Build frontend assets
npm run build

# Run all tests
php artisan test
# or
./vendor/bin/phpunit

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run a single test method
php artisan test --filter=test_method_name

# Lint with Laravel Pint
./vendor/bin/pint

# Open Tinker REPL
php artisan tinker
```

## Database Setup

Requires a local MySQL database. Configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dentista_db
DB_USERNAME=root
DB_PASSWORD=
```

The seeder populates fake data in dependency order: `TipoEmpleado` → `ClaseServicio` → `Persona` → `Corte` → `Servicio` → `Empleado` → `Cita` → `Receta` → `Pago`.

Note: `phpunit.xml` has SQLite in-memory commented out, so tests run against the real MySQL database by default. If you enable SQLite for tests, you must also update DB-related test setup accordingly.

## Architecture & Key Conventions

### Non-standard Primary Keys
All custom tables use camelCase primary key names instead of Laravel's default `id`. Every model **must** declare `$primaryKey` explicitly:

```php
protected $primaryKey = 'idPersona'; // example
```

Without this, Eloquent will default to `id` and fail to find records.

### Domain Model (Spanish column names throughout)

```
TipoEmpleado ──┐
               ├──► Empleado ──────────────────────────► Pago ◄──── Corte
Persona ────────┤                                          ▲
               └──► Persona (also referenced by)          │
                                                          │
ClaseServicio ──► Servicio ──► Cita ──► Receta            │
                               │                          │
                               └──────────────────────────┘
```

- **Persona** — patient record (nombre, apellidoP, apellidoM, celular, correoElectronico)
- **Empleado** — extends Persona via FK `idPersona`; has `usuario`, `rfc`, `contraseña`, `palabraClave`, `cambioContraseña`
- **TipoEmpleado** — catalog: employee type (dentist, receptionist, etc.)
- **ClaseServicio** — catalog: service category
- **Servicio** — dental service with `costo` (decimal) and `duracion` (time)
- **Cita** — appointment linking Persona + Servicio; has `fechaProgramada`, `hora`, `motivo`
- **Receta** — prescription linked to a Cita; contains `indicaciones`
- **Corte** — cash register cut period (`fechaInicio`, `fechaFin`, `fDeCaja`, `tEfectivo`, `tTarjeta`)
- **Pago** — payment linked to Persona + Empleado + Corte; tracks `efectivo` and `tarjeta` split

All domain tables have a `estado` boolean column (1 = active, 0 = inactive) as a soft-enable pattern — not Laravel's `SoftDeletes`.

### Current State

Models (`app/Models/`) are bare-bones — no `$primaryKey`, `$fillable`, `$casts`, or Eloquent relationships are defined yet. No controllers or business logic have been implemented. Routes only have the welcome view and the Sanctum `/api/user` stub.

The next development phase should add relationships, `$primaryKey` declarations, and controllers before implementing features.
