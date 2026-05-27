# Fase 4 - Pacientes

## 1. Objetivo

Esta fase estabiliza el modulo de pacientes/personas en sus endpoints normales, especialmente la baja logica, la visibilidad de registros activos, la validacion de datos y la consistencia con relaciones ya usadas por empleados, citas y pagos.

El objetivo funcional es que una persona inactiva no pueda seguir consultandose ni modificandose por URL directa, manteniendo la baja como actualizacion de `estado=false` y sin eliminacion fisica.

## 2. Estado inicial

Antes de la fase:

- `GET /api/personas` ya consultaba `Persona::activos()` y el filtro `search` se aplicaba sobre esa consulta.
- `POST /api/personas` creaba personas con `estado=true` y respondia mediante `PersonaResource`.
- `GET /api/personas/{persona}` cargaba la persona recibida por route model binding sin comprobar `estado`.
- `PUT/PATCH /api/personas/{persona}` actualizaba directamente la persona enlazada, aunque estuviera inactiva.
- `DELETE /api/personas/{persona}` cambiaba `estado=false`, pero podia repetirse sobre una persona ya inactiva.
- El correo se validaba por formato, pero no mediante regla `unique` en los requests, aun cuando la base tiene indice unico para `correoElectronico`.
- Existian 8 pruebas de `PersonaTest`, principalmente para listado activo, busqueda basica, alta, permiso de creacion, baja logica y consulta activa.

## 3. Problemas detectados

| ID | Problema | Resultado |
|---|---|---|
| PAC-001 | Personas inactivas visibles por URL directa en `show`. | Corregido: ahora devuelve `404`. |
| PAC-002 | Personas inactivas actualizables y baja repetible sin regla explicita. | Corregido: `update` y un segundo `destroy` devuelven `404`. |
| PAC-003 | Riesgo de inconsistencia en busqueda/listado. | Verificado: ambos operan solo sobre activos y ahora cuentan con prueba explicita para coincidencia inactiva. |
| PAC-004 | Correo duplicado dependia del error de base en lugar de validacion API. | Corregido: store y update devuelven `422` mediante `Rule::unique`. |
| PAC-005 | Riesgo de afectar Personas asociadas a Empleados. | Controlado: no se alteraron relaciones ni flujo de empleados; `EmpleadoTest` y la suite completa pasan. |

## 4. Decisiones tecnicas

La regla de visibilidad de una persona inactiva se implemento de forma localizada en `PersonaController` mediante el metodo privado `asegurarActiva(Persona $persona)`. El metodo se aplica en `show`, `update` y `destroy`, que son los endpoints normales que reciben una persona concreta.

Se eligio esta solucion porque:

- `index` y busqueda ya usan el scope `activos()` del modelo y no requerian modificacion.
- No cambia el route model binding de forma global ni altera la relacion `Persona`-`Empleado`.
- Mantiene el cambio limitado al modulo solicitado.
- Expresa una regla consistente para consultas y mutaciones directas.

Una persona con `estado=false` responde `404` en `show`, `update` y `destroy`. Se usa `404` para que un recurso dado de baja no se considere disponible en el flujo normal y para no exponer su existencia como paciente operativo.

La baja repetida se definio como `404`: el primer `DELETE` devuelve `204` y cambia el estado; una segunda solicitud ya no opera sobre un recurso activo disponible.

En validacion, `StorePersonaRequest` y `UpdatePersonaRequest` incorporaron `Rule::unique('personas', 'correoElectronico')`; el update ignora el registro actual. Asi, un correo duplicado produce una respuesta controlada `422` antes de intentar persistirlo.

La relacion con empleados se preservo sin cambios: no se modificaron `Persona`, `Empleado`, recursos de empleados, factories de empleados ni rutas.

## 5. Reglas de negocio aplicadas

- Las personas creadas desde `POST /api/personas` permanecen con `estado=true`.
- `GET /api/personas` muestra solo personas activas.
- La busqueda por nombre o apellidos muestra solo personas activas.
- `GET /api/personas/{id}` devuelve `404` si la persona esta inactiva.
- `PUT/PATCH /api/personas/{id}` devuelve `404` si la persona esta inactiva y no cambia sus datos.
- `DELETE /api/personas/{id}` realiza baja logica y responde `204` cuando la persona esta activa.
- Un segundo `DELETE` sobre una persona ya inactiva devuelve `404`.
- No existe eliminacion fisica de la persona desde este modulo.
- Admin y recepcionista conservan permisos de alta, edicion y baja; el dentista conserva el bloqueo existente para crear.
- La lectura de personas conserva el permiso actual de cualquier empleado autenticado; no se redefinieron autorizaciones en esta fase.

## 6. Convenciones aplicadas

- Se mantuvieron los nombres existentes del dominio: `Persona`, `estado`, `nombre`, `apellidoP`, `apellidoM`, `celular` y `correoElectronico`.
- El controlador orquesta la regla de disponibilidad del recurso; las validaciones de entrada permanecen en `FormRequest`.
- Se reutilizo el scope `activos()` que ya existia para listado y busqueda, evitando duplicar esa consulta.
- `PersonaResource` se mantuvo intacto para preservar el contrato JSON actual del frontend.
- No se tocaron `Empleado`, citas, servicios, pagos, cortes, recetas, catalogos ni rutas.
- La cobertura se implemento en pruebas Feature con factories, sin depender de datos manuales de la base.

## 7. Archivos modificados

| Archivo | Cambio | Motivo |
|---|---|---|
| `app/Http/Controllers/PersonaController.php` | Se agrego validacion de persona activa para `show`, `update` y `destroy`. | Bloquear consulta o modificacion directa de registros dados de baja. |
| `app/Http/Requests/StorePersonaRequest.php` | Se agrego validacion `unique` para correo. | Convertir duplicados en error API `422` consistente. |
| `app/Http/Requests/UpdatePersonaRequest.php` | Se agrego validacion `unique` ignorando la propia persona. | Impedir duplicados sin bloquear la conservacion del correo actual. |
| `tests/Feature/PersonaTest.php` | Se amplio la suite de pacientes. | Cubrir baja logica, visibilidad, validacion, busqueda y permisos. |
| `docs/FASE_4_PACIENTES.md` | Creacion de documentacion de fase. | Registrar cambios, decisiones, evidencia y pendientes. |

No se modificaron `app/Models/Persona.php`, `app/Http/Resources/PersonaResource.php`, `database/factories/PersonaFactory.php` ni `routes/api.php`, porque el scope, el contrato JSON, los datos factory y los permisos existentes resultaron suficientes para esta correccion.

## 8. Pruebas agregadas o modificadas

| Archivo de prueba | Prueba | Que valida | Resultado |
|---|---|---|---|
| `tests/Feature/PersonaTest.php` | `test_index_retorna_solo_personas_activas` | El listado excluye registros inactivos. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_busqueda_retorna_solo_personas_activas_que_coinciden` | Una coincidencia inactiva no aparece en busqueda. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_admin_puede_crear_persona_activa` | El alta responde `201` y persiste `estado=true`. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_crear_persona_rechaza_datos_invalidos_y_correo_duplicado` | Requeridos, formato/maximo y unicidad responden `422`. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_show_de_persona_inactiva_retorna_404` | La baja impide consulta por URL directa. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_actualizar_persona_activa_persiste_cambios` | Una persona activa puede editarse. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_actualizar_persona_rechaza_correo_duplicado` | El update controla unicidad del correo. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_actualizar_persona_inactiva_retorna_404_y_no_modifica_datos` | La persona inactiva no se edita. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_destroy_hace_baja_logica_y_no_elimina_el_registro` | DELETE mantiene la fila y cambia su estado. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_destroy_repetido_de_persona_inactiva_retorna_404` | Se define la semantica de baja repetida. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_persona_eliminada_no_reaparece_en_index` | Una baja desaparece del listado normal. | Pasa |
| `tests/Feature/PersonaTest.php` | `test_personas_requieren_autenticacion_para_consulta` | Las rutas conservan autenticacion. | Pasa |

## 9. Comandos ejecutados

Las pruebas se ejecutaron sobre `dentista_db_testing` mediante `APP_ENV=testing`, `DB_CONNECTION=mysql` y `DB_DATABASE=dentista_db_testing`. La base principal `dentista_db` no fue utilizada para validar esta fase.

| Comando | Resultado | Observaciones |
|---|---|---|
| `php artisan route:list --path=api/personas -v` | Correcto: 5 rutas | Se verificaron `auth:sanctum`, `empleado.activo` y roles actuales. |
| `php artisan test --filter=PersonaTest` antes del cambio | Correcto: 8 pruebas, 20 aserciones | Mostro la cobertura inicial, sin casos de URL directa inactiva. |
| `php -l app/Http/Controllers/PersonaController.php` y requests/tests modificados | Correcto | Sin errores de sintaxis. |
| `php artisan test --filter=PersonaTest` | Correcto: 16 pruebas, 47 aserciones | Cubre las reglas nuevas del modulo. |
| `php artisan test --filter=EmpleadoTest` | Correcto: 9 pruebas, 20 aserciones | Regresion de la relacion Persona-Empleado. |
| `php artisan test --filter=PagoTest` | Correcto: 20 pruebas, 62 aserciones | Regresion de Fase 3. |
| `php artisan test --filter=CorteTest` | Correcto: 12 pruebas, 30 aserciones | Regresion de Fase 3. |
| `php artisan config:clear` | Correcto | Preparacion del entorno testing. |
| `php artisan cache:clear` | Correcto | Preparacion del entorno testing. |
| `php artisan migrate:fresh --seed --database=mysql --force` | Correcto | Reinicio exclusivo de `dentista_db_testing`. |
| `php artisan migrate:status --database=mysql` | Correcto | Se verificaron 17 migraciones aplicadas. |
| `php artisan test` | Correcto: 102 pruebas, 253 aserciones | Suite completa verde; duracion aproximada 4.75 s. |
| `git diff --check` | Correcto | Sin errores de espacios en cambios locales. |

Detalle del reinicio de datos:

| Dato | Valor |
|---|---|
| Comando ejecutado | `php artisan migrate:fresh --seed --database=mysql --force` |
| Base usada | `dentista_db_testing` |
| Motivo | Confirmar de forma reproducible el modulo de pacientes y regresiones. |
| Resultado | Migraciones y seeders completados; suite completa verde. |
| Datos afectados | Solo datos temporales de testing; no afecto la base principal. |

## 10. Resultado final

- `php artisan test --filter=PersonaTest` paso: 16 pruebas, 47 aserciones.
- `php artisan test --filter=EmpleadoTest` paso: 9 pruebas, 20 aserciones.
- `php artisan test --filter=PagoTest` paso: 20 pruebas, 62 aserciones.
- `php artisan test --filter=CorteTest` paso: 12 pruebas, 30 aserciones.
- `php artisan test` paso completo: 102 pruebas, 253 aserciones.
- PAC-001 quedo cerrado: una persona inactiva ya no devuelve `200` en consulta directa.
- PAC-002 quedo cerrado: una persona inactiva no puede actualizarse ni darse de baja nuevamente desde el flujo normal.
- Seguridad, agenda, pagos y cortes permanecen en verde dentro de la suite completa.

## 11. Riesgos o pendientes

- No existe una estrategia funcional de deteccion o fusion de pacientes duplicados mas alla de la unicidad del correo cuando se proporciona.
- La entidad `Persona` representa tanto pacientes como datos personales vinculados a empleados; si el dominio necesita separar expedientes clinicos de personas laborales, requerira una fase de diseno independiente.
- Los datos de contacto permanecen visibles para todo empleado autenticado que puede consultar personas, conforme a las rutas actuales. Una matriz mas restrictiva de exposicion de datos personales debe definirse como decision de negocio y autorizacion futura.
- No se agregaron reglas nuevas de formato telefonico porque el contrato actual solo define cadena requerida de hasta 20 caracteres; endurecerlo requiere acordar formatos nacionales/internacionales aceptados.

## 12. Notas para el siguiente desarrollador

- La regla principal para evitar operar pacientes inactivos esta en `PersonaController::asegurarActiva()`.
- La busqueda y listado dependen de `Persona::activos()`, que ya existia antes de esta fase.
- La unicidad de correo en altas y ediciones se valida en los `FormRequest` del modulo.
- `PersonaTest` protege la baja logica, la no visibilidad de inactivos, la validacion y los permisos basicos.
- El frontend debe tratar un `404` en `show`, `update` o DELETE repetido como persona no disponible en el flujo normal.
- En fases futuras no debe eliminarse fisicamente una Persona ni modificarse su relacion con Empleado sin revisar las regresiones de empleados, citas y pagos.
