# Fase 7 - Recuperación por palabra clave

## 1. Objetivo
Implementar el endpoint público requerido por el frontend para recuperar contraseña mediante usuario, palabra clave y nueva contraseña.

La fase cierra el pendiente P0 donde `POST /api/recover-password-keyword` respondía 404 y permite que un empleado actualice su contraseña sin sesión activa, manteniendo las reglas de seguridad ya corregidas en fases previas: contraseñas hasheadas, revocación de tokens y rutas protegidas con Sanctum.

## 2. Estado inicial
- El endpoint `POST /api/recover-password-keyword` no existía y devolvía 404.
- El frontend ya enviaba `usuario`, `palabraClave`, `new_password` y `new_password_confirmation`.
- El modelo autenticable real seguía siendo `App\Models\Empleado`.
- El login normal ya validaba `usuario` y `contraseña`.
- El reset administrativo ya revocaba tokens.
- `palabraClave` se hasheaba en creación/edición de empleados, pero la factory generaba el valor en texto plano.

## 3. Contrato implementado
| Elemento | Valor |
|---|---|
| Método | `POST` |
| Ruta | `/api/recover-password-keyword` |
| Autenticación | Pública, no requiere Bearer token |
| Rate limit | `throttle:10,1` más middleware global `api` |
| Payload | `usuario`, `palabraClave`, `new_password`, `new_password_confirmation` |
| Respuesta exitosa | `200` con `{ "message": "Contraseña actualizada correctamente." }` |
| Usuario inexistente o inactivo | `404` |
| Palabra clave incorrecta | `401` |
| Validación de campos | `422` con errores por campo |

Payload esperado:

```json
{
  "usuario": "test.admin@dentalsys.local",
  "palabraClave": "ClaveAdmin123!",
  "new_password": "NuevaPassword123!",
  "new_password_confirmation": "NuevaPassword123!"
}
```

## 4. Decisiones de seguridad
- La validación de entrada quedó en `RecoverPasswordKeywordRequest`, siguiendo la convención Laravel de FormRequest.
- La recuperación se implementó en `AuthController` porque pertenece al flujo público de autenticación, no a administración interna de empleados.
- `palabraClave` se valida con `Hash::check()` cuando el valor almacenado es un hash reconocido.
- Para no romper datos legacy, si se detecta una `palabraClave` antigua en texto plano y coincide, se permite la recuperación y se rehashea inmediatamente dentro de la misma transacción.
- Si el usuario no existe o está inactivo se responde `404`. Esta decisión mantiene el contrato solicitado, aunque se deja como pendiente evaluar una respuesta más opaca para reducir enumeración.
- Si la palabra clave es incorrecta se responde `401`.
- La contraseña nueva se guarda con `Hash::make()`.
- Al recuperar contraseña correctamente se actualiza `cambioContraseña=false`.
- Al recuperar contraseña correctamente se eliminan todos los tokens del empleado mediante `tokens()->delete()`.
- La actualización de contraseña, posible migración de palabra clave legacy y revocación de tokens se ejecutan dentro de `DB::transaction()`.
- La ruta pública tiene rate limit específico `throttle:10,1`.

## 5. Archivos modificados
| Archivo | Cambio | Motivo |
|---|---|---|
| `app/Http/Controllers/AuthController.php` | Se agregó `recoverPasswordKeyword()` y validación segura de `palabraClave` | Implementar recuperación pública, hash de contraseña, revocación de tokens y soporte legacy controlado |
| `app/Http/Requests/RecoverPasswordKeywordRequest.php` | Archivo nuevo | Centralizar validación del payload requerido por frontend |
| `routes/api.php` | Se agregó `POST /api/recover-password-keyword` con `throttle:10,1` | Exponer endpoint público protegido por rate limit |
| `database/factories/EmpleadoFactory.php` | `palabraClave` ahora se genera hasheada | Evitar que pruebas nuevas creen empleados inseguros por defecto |
| `database/seeders/DatabaseSeeder.php` | Se agregaron palabras clave conocidas y hasheadas para usuarios controlados | Permitir pruebas manuales/front con usuarios seed sin guardar palabra clave en texto plano |
| `tests/Feature/AuthTest.php` | Se agregaron pruebas de recuperación por palabra clave | Cubrir contrato, seguridad, tokens y regresión de login |
| `docs/FASE_7_RECUPERACION_PALABRA_CLAVE.md` | Archivo nuevo | Documentar qué se hizo, cómo se hizo y resultados |

## 6. Pruebas agregadas o modificadas
| Archivo de prueba | Prueba | Qué valida | Resultado |
|---|---|---|---|
| `tests/Feature/AuthTest.php` | `test_recover_password_keyword_actualiza_contrasena_y_permite_login` | Recuperación correcta, mensaje esperado, hash de nueva contraseña, login con nueva contraseña y rechazo de anterior | Pasó |
| `tests/Feature/AuthTest.php` | `test_recover_password_keyword_usuario_inexistente_retorna_404` | Usuario inexistente responde 404 | Pasó |
| `tests/Feature/AuthTest.php` | `test_recover_password_keyword_palabra_clave_incorrecta_retorna_401` | Palabra clave incorrecta responde 401 | Pasó |
| `tests/Feature/AuthTest.php` | `test_recover_password_keyword_valida_payload` | Campos requeridos, confirmación y contraseña débil responden 422 | Pasó |
| `tests/Feature/AuthTest.php` | `test_recover_password_keyword_revoca_tokens_previos` | Tokens emitidos antes de la recuperación quedan inválidos | Pasó |
| `tests/Feature/AuthTest.php` | `test_recover_password_keyword_migra_palabra_clave_legacy_en_texto_plano` | Dato legacy en texto plano se acepta sólo si coincide y se rehashea | Pasó |
| `tests/Feature/EmpleadoTest.php` | Suite existente | Reset administrativo, baja y creación de empleados siguen funcionando | Pasó |

## 7. Comandos ejecutados
| Comando | Resultado | Observaciones |
|---|---|---|
| `php -l app\Http\Controllers\AuthController.php` | Sin errores de sintaxis | Validación posterior a cambios |
| `php -l app\Http\Requests\RecoverPasswordKeywordRequest.php` | Sin errores de sintaxis | Validación de request nuevo |
| `php -l database\factories\EmpleadoFactory.php` | Sin errores de sintaxis | Validación de factory |
| `php -l database\seeders\DatabaseSeeder.php` | Sin errores de sintaxis | Validación de seeder |
| `php -l tests\Feature\AuthTest.php` | Sin errores de sintaxis | Validación de pruebas |
| `php artisan config:clear` | Correcto | Ejecutado en entorno local antes de pruebas |
| `php artisan cache:clear` | Correcto | Ejecutado en entorno local antes de pruebas |
| `php artisan migrate:fresh --seed --database=mysql --force` | Correcto | Ejecutado con `APP_ENV=testing`, `DB_DATABASE=dentista_db_testing`; reinició datos de testing |
| `php artisan migrate:status --database=mysql` | Correcto | Confirmó migraciones aplicadas en testing |
| `php artisan route:list --path=api/recover-password-keyword -v` | Correcto | Confirmó ruta pública con `api` y `ThrottleRequests:10,1` |
| `php artisan test --filter=AuthTest` | 19 pruebas pasaron, 55 aserciones | Validación principal de Fase 7 |
| `php artisan test --filter=EmpleadoTest` | 9 pruebas pasaron, 20 aserciones | Regresión de empleados/reset administrativo |
| `php artisan test` | 136 pruebas pasaron, 432 aserciones | Suite completa verde |

Base usada:

- `dentista_db_testing`.
- Se ejecutó `migrate:fresh --seed` sólo sobre la base de testing.
- No se usó la base principal para pruebas destructivas de esta fase.

## 8. Resultado final
- `AuthTest` pasó completo: 19 pruebas, 55 aserciones.
- `EmpleadoTest` pasó completo: 9 pruebas, 20 aserciones.
- `php artisan test` pasó completo: 136 pruebas, 432 aserciones.
- El endpoint P0 `POST /api/recover-password-keyword` quedó implementado.
- La recuperación funciona sin token Bearer.
- La contraseña nueva se guarda hasheada.
- Los tokens previos se revocan tras recuperación exitosa.
- El login con contraseña anterior falla y el login con contraseña nueva funciona.
- La ruta tiene rate limit específico.

## 9. Riesgos o pendientes
- Evaluar si conviene responder siempre un mensaje genérico para usuario inexistente y palabra clave incorrecta, reduciendo enumeración de usuarios.
- Agregar bloqueo progresivo por intentos fallidos si el volumen de uso lo requiere.
- Considerar captcha o verificación adicional si este endpoint queda expuesto públicamente en producción.
- Estandarizar nombres de campos de contraseña con y sin `ñ` en una fase futura si el equipo decide reducir fricción de integración.
- Agregar logging/auditoría de recuperación sin registrar valores sensibles.

## 10. Notas para frontend
- URL: `POST /api/recover-password-keyword`.
- No requiere token Bearer.
- Enviar exactamente:
  - `usuario`
  - `palabraClave`
  - `new_password`
  - `new_password_confirmation`
- Éxito:

```json
{
  "message": "Contraseña actualizada correctamente."
}
```

- `401`: palabra clave incorrecta.
- `404`: usuario no encontrado o empleado inactivo.
- `422`: error de validación por campo; revisar `errors`.
- Después de éxito, redirigir a login. Cualquier sesión/token previo de ese empleado queda revocado.
