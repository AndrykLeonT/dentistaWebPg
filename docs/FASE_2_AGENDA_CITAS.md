# Fase 2 - Agenda y citas

## 1. Objetivo

Esta fase corrige la disponibilidad de agenda para impedir colisiones y traslapes de citas activas asignadas al mismo dentista. La regla aplica tanto al crear como al actualizar una cita y toma la duración real del servicio asociado.

## 2. Estado inicial

- Las citas ya contaban con `idEmpleado` para asignar un dentista.
- `StoreCitaRequest` verificaba que el empleado fuera dentista activo y rechazaba coincidencias de misma fecha y misma hora.
- `UpdateCitaRequest` sólo verificaba el tipo del empleado cuando se enviaba `idEmpleado`; no comprobaba disponibilidad.
- No se detectaban traslapes por duración. Una cita de `10:00` a `11:00` podía coexistir con otra del mismo dentista a las `10:30`.
- `CitaResource` ya entregaba `dentista: { id, nombreCompleto }` y toleraba `dentista: null` en registros históricos.
- La duración de referencia existe en `servicios.duracion`, columna `TIME` con formato `HH:MM:SS`. El campo nullable `citas.duracion` no se utiliza para decidir disponibilidad.

## 3. Problemas detectados

| ID | Problema | Estado inicial |
|---|---|---|
| CIT-001 | Una actualización podía mover una cita al horario ocupado por otra cita activa del mismo dentista. | Confirmado por inspección: `UpdateCitaRequest` no consultaba colisiones. |
| CIT-002 | La alta sólo comparaba igualdad de hora y no intervalos calculados con duración. | Confirmado por inspección de `StoreCitaRequest`. |
| CIT-003 | La validación de tipo dentista usaba minúsculas, pero no eliminaba espacios laterales. | Confirmado por inspección; podía rechazar ` DENTISTA `. |
| CIT-004 | Existen citas históricas posibles con `idEmpleado=null`. | La migración permite `null`; el resource debía seguir respondiendo sin excepción. |

## 4. Decisiones técnicas

La lógica reusable quedó en `app/Services/DisponibilidadCitaService.php`. Se eligió un servicio de dominio porque la regla combina:

- Validación de que el empleado asignado esté activo y sea dentista.
- Consulta de citas activas del mismo dentista en la misma fecha.
- Cálculo de intervalos usando el servicio de cada cita.
- Exclusión de la cita actual en actualización.

Los `FormRequest` siguen siendo responsables de aplicar la regla al input HTTP:

- `StoreCitaRequest` valida el dentista y consulta traslapes para una cita nueva.
- `UpdateCitaRequest` compone el estado efectivo de la cita con los campos recibidos y los valores actuales; sólo exige asignar dentista a una cita histórica si se modifica su información de agenda.

La hora de inicio se construye con `fechaProgramada + hora`. La hora final se obtiene sumando los segundos equivalentes a `Servicio::duracion`, almacenada como `HH:MM:SS`.

Dos citas se consideran traslapadas cuando:

```text
inicioNueva < finExistente && finNueva > inicioExistente
```

Esta condición permite citas consecutivas: una cita que comienza exactamente cuando termina otra no colisiona.

En actualización se excluye el registro editado por `idCita`, evitando que una cita colisione consigo misma cuando conserva su horario.

El tipo del empleado se normaliza con `strtolower(trim(nombre)) === 'dentista'`, por lo que acepta variaciones como `Dentista`, `DENTISTA` o ` DENTISTA `.

Los conflictos de disponibilidad y de dentista asignado se reportan como validación HTTP `422`, manteniendo el contrato vigente de la API.

## 5. Reglas de negocio aplicadas

| Regla | Implementación |
|---|---|
| Dentista obligatorio en alta | `StoreCitaRequest` exige `idEmpleado`. |
| Dentista activo | `DisponibilidadCitaService::esDentistaActivo()`. |
| Tipo dentista normalizado | Comparación con `trim()` y minúsculas. |
| Recurso que ocupa agenda | `idEmpleado` del dentista. |
| Fecha comparable | Sólo citas de la misma `fechaProgramada`. |
| Estado comparable | Sólo citas activas (`estado=true`). |
| Duración usada | `servicios.duracion` de la cita candidata y de cada cita existente. |
| Conflicto exacto | Mismo dentista y mismo intervalo devuelve `422`. |
| Conflicto parcial | Cualquier traslape de intervalos devuelve `422`. |
| Citas consecutivas | Permitidas cuando el inicio coincide con el fin de la anterior. |
| Dentistas distintos | Permitidos en el mismo horario, al no existir otro recurso compartido definido. |
| Update parcial | Utiliza valores actuales cuando no se envía fecha, hora, servicio o dentista. |
| Update de sí misma | Excluye el `idCita` editado. |
| Histórico sin dentista | El listado continúa retornando `dentista: null`; si se modifica agenda, debe asignarse un dentista válido. |

## 6. Convenciones aplicadas

- Se mantuvieron los nombres de dominio existentes: `Cita`, `Servicio`, `Empleado`, `idEmpleado`, `fechaProgramada`, `hora`, `duracion` y `estado`.
- La validación HTTP permanece en `FormRequest`; el controller no recibió lógica de disponibilidad.
- La operación reutilizable se ubicó en `app/Services`, evitando duplicar consultas y cálculo temporal entre alta y actualización.
- `CitaResource` no fue modificado porque ya mantiene el contrato requerido y tolera `dentista=null`.
- No se modificaron rutas, autenticación, pagos, cortes, personas, empleados, servicios, recetas ni bajas lógicas generales.
- No se agregaron dependencias.

## 7. Archivos modificados

| Archivo | Cambio | Motivo |
|---|---|---|
| `app/Services/DisponibilidadCitaService.php` | Creación | Centralizar validación de dentista activo y detección de traslapes por duración. |
| `app/Http/Requests/StoreCitaRequest.php` | Modificación | Reutilizar la validación por intervalos al crear citas. |
| `app/Http/Requests/UpdateCitaRequest.php` | Modificación | Validar disponibilidad con updates parciales y excluir la cita actual. |
| `tests/Feature/CitaTest.php` | Modificación | Cubrir reglas de disponibilidad, normalización y compatibilidad histórica. |
| `docs/FASE_2_AGENDA_CITAS.md` | Creación | Documentar decisiones, pruebas y resultado de la fase. |

Archivos revisados sin requerir modificación: `CitaController.php`, `CitaResource.php`, `Cita.php`, `CitaFactory.php` y las migraciones actuales de citas/servicios.

## 8. Pruebas agregadas o modificadas

| Archivo de prueba | Prueba | Qué valida | Resultado |
|---|---|---|---|
| `tests/Feature/CitaTest.php` | `test_recepcionista_puede_crear_cita` | Alta correcta e inclusión de dentista en la respuesta. | Pasa |
| `tests/Feature/CitaTest.php` | `test_crear_cita_rechaza_empleado_que_no_es_dentista` | Rechazo `422` de rol incorrecto. | Pasa |
| `tests/Feature/CitaTest.php` | `test_crear_cita_rechaza_dentista_inactivo` | Rechazo `422` de dentista dado de baja. | Pasa |
| `tests/Feature/CitaTest.php` | `test_crear_cita_acepta_tipo_dentista_normalizado` | Acepta mayúsculas y espacios laterales en el tipo. | Pasa |
| `tests/Feature/CitaTest.php` | `test_colision_de_horario_por_dentista_retorna_422` | Rechaza mismo inicio para el mismo dentista. | Pasa |
| `tests/Feature/CitaTest.php` | `test_mismo_horario_es_permitido_para_distinto_dentista` | Permite agenda paralela con otro dentista. | Pasa |
| `tests/Feature/CitaTest.php` | `test_crear_cita_rechaza_traslape_por_duracion` | Rechaza alta a mitad de una cita existente. | Pasa |
| `tests/Feature/CitaTest.php` | `test_crear_cita_permite_horario_consecutivo` | Permite comenzar exactamente al terminar la cita previa. | Pasa |
| `tests/Feature/CitaTest.php` | `test_cita_inactiva_no_bloquea_disponibilidad` | Ignora bajas lógicas al reservar horario. | Pasa |
| `tests/Feature/CitaTest.php` | `test_update_manteniendo_datos_de_agenda_no_colisiona_consigo_misma` | Excluye la cita editada de su propia validación. | Pasa |
| `tests/Feature/CitaTest.php` | `test_update_sin_cambiar_agenda_sigue_funcionando` | Un cambio no horario conserva el flujo existente. | Pasa |
| `tests/Feature/CitaTest.php` | `test_update_rechaza_horario_de_otra_cita_del_mismo_dentista` | Cierra CIT-001 para igualdad de horario en update. | Pasa |
| `tests/Feature/CitaTest.php` | `test_update_permite_horario_ocupado_por_otro_dentista` | No bloquea agenda de otro profesional. | Pasa |
| `tests/Feature/CitaTest.php` | `test_update_rechaza_traslape_por_duracion_del_nuevo_servicio` | Usa la duración del servicio seleccionado en update. | Pasa |
| `tests/Feature/CitaTest.php` | `test_cita_historica_sin_dentista_no_rompe_listado` | Mantiene `dentista: null` en registros antiguos. | Pasa |

## 9. Comandos ejecutados

Todas las operaciones de base de datos de esta fase se ejecutaron contra `dentista_db_testing`. Se reinició únicamente esa base para probar el esquema y los seeders; no se alteró la base principal configurada para uso normal.

| Comando | Resultado | Observaciones |
|---|---|---|
| Lectura de archivos de citas, servicio, factories, migraciones y tests | Correcto | Confirmó que `servicios.duracion` es `TIME` y que el resource ya tolera dentista nulo. |
| `php -l app/Services/DisponibilidadCitaService.php` | Correcto | Sin errores de sintaxis. |
| `php -l app/Http/Requests/StoreCitaRequest.php` | Correcto | Sin errores de sintaxis. |
| `php -l app/Http/Requests/UpdateCitaRequest.php` | Correcto | Sin errores de sintaxis. |
| `php -l tests/Feature/CitaTest.php` | Correcto | Sin errores de sintaxis. |
| `php artisan test --filter=CitaTest` con `DB_DATABASE=dentista_db_testing` | Correcto | `23` pruebas, `52` aserciones. |
| `php artisan config:clear` con entorno testing | Correcto | Limpieza previa a la regresión final. |
| `php artisan cache:clear` con entorno testing | Correcto | Limpieza previa a la regresión final. |
| `php artisan migrate:fresh --seed --database=mysql --force` con `DB_DATABASE=dentista_db_testing` | Correcto | Reinició datos de prueba de `dentista_db_testing`; aplicó 17 migraciones y seeders. |
| `php artisan migrate:status --database=mysql` con `DB_DATABASE=dentista_db_testing` | Correcto | Todas las migraciones, incluida `add_id_empleado_to_citas_table`, aparecen como `Ran`. |
| `php artisan test` con `DB_DATABASE=dentista_db_testing` | Correcto | `74` pruebas, `157` aserciones, `0` fallos. |

## 10. Resultado final

- `php artisan test --filter=CitaTest` pasó: `23` pruebas y `52` aserciones.
- `php artisan test` pasó completo: `74` pruebas y `157` aserciones.
- CIT-001 quedó cerrado: actualizar una cita hacia un intervalo ocupado por otra cita activa del mismo dentista devuelve `422`.
- CIT-002 quedó cerrado en la capa de aplicación: altas y actualizaciones rechazan traslapes calculados con la duración del servicio.
- CIT-003 quedó cerrado: la validación de dentista acepta variaciones de mayúsculas y espacios laterales.
- CIT-004 quedó cubierto: un registro histórico sin dentista se lista correctamente con `dentista: null`.
- La Fase 1 no se rompió: las pruebas de autenticación/tokens continúan aprobadas dentro de la suite completa.

## 11. Riesgos o pendientes

- La exclusión de traslapes se resuelve en la aplicación; no existe una protección de base de datos ante dos reservas concurrentes que entren simultáneamente antes de persistir.
- La regla actual considera agenda por dentista. Si posteriormente se requiere bloquear consultorio, sillón, equipo o sucursal, deberán modelarse esos recursos y agregar pruebas.
- La regla se limita a citas de la misma `fechaProgramada`, conforme al alcance definido. Si un servicio pudiera cruzar medianoche, deberá decidirse cómo reservar disponibilidad del día siguiente.
- El campo `citas.duracion` continúa existiendo como dato opcional histórico, pero la agenda usa `servicios.duracion` como fuente de verdad; cualquier cambio futuro de contrato debe preservar esa decisión o documentar su reemplazo.

## 12. Notas para el siguiente desarrollador

- La lógica central está en `app/Services/DisponibilidadCitaService.php`.
- `StoreCitaRequest` y `UpdateCitaRequest` son los puntos donde los conflictos se convierten en respuesta HTTP `422`.
- Las pruebas que protegen disponibilidad están en `tests/Feature/CitaTest.php`, especialmente las de traslape, update, horario consecutivo e histórico sin dentista.
- En fases futuras no debe volver a validarse sólo igualdad de hora: debe preservarse la comparación de intervalos por duración.
- El frontend debe enviar `idEmpleado` al crear una cita y admitir que citas antiguas respondan `dentista: null`.
- Las rutas autenticadas deben conservar `auth:sanctum` seguido de `empleado.activo`; esta fase no modifica esa protección.
