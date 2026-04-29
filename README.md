# Sistema de Gestión para Consultorio Dental

## Introducción
Este proyecto es una aplicación web desarrollada en **Laravel 11** diseñada para administrar los procesos internos de un consultorio dental. La arquitectura del backend está estructurada utilizando el patrón MVC (Modelo-Vista-Controlador) y una base de datos relacional robusta.

## Descripción
El sistema permite gestionar el flujo completo de la clínica, abarcando las siguientes entidades principales:
- **Catálogos base:** Tipos de empleados y Clases de servicios.
- **Entidades principales:** Personas (Pacientes) y Empleados.
- **Transacciones:** Citas médicas, Servicios prestados y Emisión de recetas.
- **Módulo financiero:** Control de pagos y Cortes de caja.

La base de datos está diseñada respetando la integridad referencial (llaves foráneas) y cuenta con un sistema de poblado de datos ficticios (Faker) para facilitar pruebas y desarrollo.


## Construir la base de datos

## Pasos de Instalación y Ejecución
Crea un archivo llamado .env en la raíz del proyecto y configurar la conexión a la base de datos MySQL local:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dentista_db
DB_USERNAME=root
DB_PASSWORD=

