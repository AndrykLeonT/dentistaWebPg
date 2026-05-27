# Fase 1 - Seguridad de autenticación y tokens

## 1. Objetivo

Esta fase corrige la continuidad indebida de sesiones de empleados desactivados. Antes del cambio, un token Bearer emitido a un empleado activo podía seguir utilizándose después de que su registro cambiara a `estado=false`.

Los objetivos concretos fueron:

- Rechazar tokens de empleados que ya no estén activos en cada petición autenticada.
- Revocar tokens existentes cuando un administrador desactive un empleado.
- Establecer expiración configurable para tokens de Laravel Sanctum.
- Mantener los contratos y permisos actuales de login, logout, `/api/me`, cambio de contraseña y roles.

## 2. Estado inicial

- `POST /api/login` autenticaba únicamente empleados activos y generaba un token Sanctum para credenciales válidas.
- `POST /api/logout` eliminaba correctamente el token utilizado en la petición.
- El login de un empleado ya inactivo era rechazado con `401`.
- La baja lógica de un empleado cambiaba `estado` a `false`, pero no eliminaba tokens emitidos anteriormente.
- Una petición con token previo de un empleado desactivado continuaba autenticada porque las rutas sólo exigían `auth:sanctum`.
- `config/sanctum.php` tenía `expiration` en `null`, por lo que los tokens no vencían por política global.

## 3. Decisiones técnicas tomadas

Se creó el middleware `EnsureEmpleadoIsActive` y se aplicó dentro del grupo de rutas que ya usa `auth:sanctum`. La validación es transversal a toda la API autenticada y evita repetir la comprobación de `estado` en cada controlador o middleware de rol.

El middleware recibe al usuario ya autenticado por Sanctum. Cuando es un `Empleado` con `estado=false`, elimina el token actual si existe y devuelve `401` con el mensaje `La cuenta del empleado esta inactiva.`. Se eligió `401` porque la credencial presentada dejó de representar una sesión válida y el frontend debe descartar el token y regresar al inicio de sesión; no se trata sólo de falta de permisos para una operación concreta.

La desactivación mediante `DELETE /api/empleados/{empleado}` ahora ejecuta en una misma `DB::transaction()` el cambio a `estado=false` y la eliminación de todos los tokens del empleado. Así no queda una baja aplicada con sesiones aún utilizables si una de las acciones falla.

El reset administrativo de contraseña también revoca todos los tokens del empleado dentro de una transacción. Se considera una acción crítica de recuperación de credenciales: después de cambiar la contraseña temporal, cualquier sesión anterior debe dejar de ser confiable. El endpoint autenticado `change-password` mantiene su comportamiento vigente y no revoca su propio token, para conservar el flujo válido del empleado que actualiza su contraseña durante una sesión autorizada.

La expiración global se configuró como:

```php
'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 480),
```

`SANCTUM_TOKEN_EXPIRATION=480` equivale a ocho horas y quedó declarada en `.env.example`. Cada entorno puede ajustar el valor mediante variable de entorno sin modificar código.

## 4. Convenciones aplicadas

- Se usó el nombre de dominio `Empleado` y el atributo existente `estado`, manteniendo la nomenclatura en español del proyecto.
- La regla transversal de seguridad quedó en `app/Http/Middleware`, de acuerdo con la responsabilidad de un middleware Laravel.
- El controlador de empleados conserva la orquestación HTTP y usa `DB::transaction()` sólo en las operaciones críticas compuestas.
- El alias `empleado.activo` sigue el estilo de aliases existente, incluido `rol`.
- La configuración de expiración se mantuvo en `config/sanctum.php` con una variable de entorno documentada, sin nuevas dependencias.
- No se cambiaron nombres de rutas ni formatos JSON de respuestas exitosas existentes.

## 5. Archivos modificados

| Archivo | Cambio | Motivo |
|---|---|---|
| `app/Http/Middleware/EnsureEmpleadoIsActive.php` | Creación de middleware | Rechazar empleados inactivos y revocar el token presentado. |
| `app/Http/Kernel.php` | Registro de alias `empleado.activo` | Permitir aplicar el middleware a las rutas API. |
| `routes/api.php` | Adición del middleware a rutas autenticadas | Validar el estado en toda petición protegida por Sanctum. |
| `app/Http/Controllers/EmpleadoController.php` | Transacciones y revocación de tokens en baja/reset | Cerrar sesiones ante baja o reset crítico de credenciales. |
| `config/sanctum.php` | Expiración configurable con valor por defecto de 480 minutos | Evitar tokens indefinidos. |
| `.env.example` | Variable `SANCTUM_TOKEN_EXPIRATION=480` | Documentar configuración requerida por entorno. |
| `tests/Feature/AuthTest.php` | Pruebas de baja, bloqueo, expiración y reutilización tras logout | Cubrir la política de sesión y token. |
| `tests/Feature/EmpleadoTest.php` | Aserciones de revocación en baja y reset | Cubrir acciones administrativas críticas. |
| `docs/FASE_1_SEGURIDAD_TOKENS.md` | Creación de reporte técnico | Registrar decisiones, evidencia y resultado de la fase. |

## 6. Pruebas agregadas o modificadas

| Archivo de prueba | Nombre de la prueba | Qué valida | Resultado |
|---|---|---|---|
| `tests/Feature/AuthTest.php` | `test_login_con_credenciales_validas_retorna_token` | Empleado activo conserva login exitoso y token. | Pasa |
| `tests/Feature/AuthTest.php` | `test_login_de_empleado_inactivo_retorna_401` | Empleado inactivo no inicia sesión. | Pasa |
| `tests/Feature/AuthTest.php` | `test_token_previo_de_empleado_desactivado_ya_no_permite_acceso` | Token anterior a una baja no accede a `/api/me`. | Pasa |
| `tests/Feature/AuthTest.php` | `test_empleado_desactivado_no_puede_ejecutar_operaciones_de_su_rol` | Token anterior no ejecuta una operación antes permitida. | Pasa |
| `tests/Feature/AuthTest.php` | `test_middleware_revoca_token_si_empleado_esta_inactivo` | La defensa por petición revoca un token aún presente. | Pasa |
| `tests/Feature/AuthTest.php` | `test_token_expirado_no_permite_acceso` | La expiración configurada impide usar un token vencido. | Pasa |
| `tests/Feature/AuthTest.php` | `test_logout_elimina_el_token_actual` | Logout sigue revocando el token y éste no puede reutilizarse. | Pasa |
| `tests/Feature/EmpleadoTest.php` | `test_admin_puede_resetear_contraseña` | Reset administrativo revoca tokens previos. | Pasa |
| `tests/Feature/EmpleadoTest.php` | `test_destroy_desactiva_empleado` | Baja lógica elimina todos los tokens del empleado. | Pasa |
| Suite existente | Pruebas de roles en módulos protegidos | Admin, recepcionista y dentista mantienen permisos previstos. | Pasa |

En las pruebas que efectúan peticiones con tokens distintos dentro del mismo método se limpia el guard resuelto entre solicitudes. Esto evita que el estado interno del cliente de pruebas sustituya la validación Bearer que ocurre en solicitudes HTTP independientes.

## 7. Comandos ejecutados

Todas las operaciones de base de datos usadas para pruebas se ejecutaron con `DB_DATABASE=dentista_db_testing`; no se reconstruyó `dentista_db`.

| Comando | Resultado | Observaciones |
|---|---|---|
| `php -l` sobre archivos PHP modificados | Correcto | Sin errores de sintaxis. |
| `php artisan config:clear` | Correcto | Ejecutado con entorno `testing` y base de testing. |
| `php artisan cache:clear` | Correcto | Limpieza previa a la validación. |
| `php artisan migrate:fresh --seed --database=mysql --force` | Correcto | Ejecutado sólo sobre `dentista_db_testing`. |
| `php artisan test --filter=AuthTest` (primera ejecución) | Falló: 4 pruebas | Permitió ajustar la simulación de múltiples peticiones y el envejecimiento del token dentro del test. |
| `php artisan test --filter=AuthTest` (ejecución final) | Correcto: 13 pruebas, 29 aserciones | Validación focalizada de autenticación y tokens. |
| `php artisan test --filter=EmpleadoTest` | Correcto: 9 pruebas, 20 aserciones | Validación focalizada de baja y reset administrativo. |
| `php artisan test` | Correcto: 58 pruebas, 119 aserciones | Suite completa de regresión. |
| `php artisan route:list --path=api -v` | Correcto | Las 51 rutas API protegidas muestran `EnsureEmpleadoIsActive`; login permanece público. |
| `php artisan config:show sanctum` | Correcto | `expiration` resuelve a `480`. |

## 8. Resultado final de la fase

La ejecución final de `php artisan test` pasó completa: `58` pruebas y `119` aserciones sin fallos.

El problema original quedó corregido:

- El token de un empleado desactivado ya no permite acceder a `/api/me`.
- El empleado desactivado ya no puede ejecutar operaciones de su rol con un token anterior.
- La baja de empleado elimina todos sus tokens activos en una operación transaccional.
- Un token que encuentre al empleado inactivo durante una petición también se revoca.
- Sanctum aplica una expiración global configurable de ocho horas por defecto.
- Login, logout, `/api/me`, `change-password` y las verificaciones existentes de roles continúan pasando.

## 9. Riesgos o pendientes detectados

- Cada ambiente desplegado debe declarar `SANCTUM_TOKEN_EXPIRATION` o aceptar el valor por defecto de `480`; cambiar sólo `.env.example` no altera configuraciones ya desplegadas.
- Sanctum rechaza tokens vencidos, pero los registros expirados pueden permanecer almacenados hasta ejecutar una política operativa de limpieza, por ejemplo el comando de pruning de Sanctum en una tarea programada.
- `change-password` conserva el token de la sesión que realiza el cambio. Es una decisión compatible con el flujo actual; si en el futuro se exige cierre global de sesiones ante todo cambio de contraseña, deberá definirse el comportamiento esperado y agregarse prueba específica.

## 10. Notas para el siguiente desarrollador

- La comprobación principal por petición está en `app/Http/Middleware/EnsureEmpleadoIsActive.php` y se aplica desde `routes/api.php`.
- La revocación preventiva ante baja o reset está en `EmpleadoController::destroy()` y `EmpleadoController::resetPassword()`.
- Las pruebas que protegen el comportamiento están principalmente en `tests/Feature/AuthTest.php` y `tests/Feature/EmpleadoTest.php`.
- Fases futuras deben conservar el orden `auth:sanctum` seguido de `empleado.activo` para todas las rutas autenticadas y no reintroducir accesos de empleados desactivados.
