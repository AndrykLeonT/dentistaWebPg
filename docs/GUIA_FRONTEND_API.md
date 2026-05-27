# Guía frontend para consumir la API

## 1. Propósito
Este documento es una guía práctica y operativa estructurada para que desarrolladores frontend o agentes de IA puedan diseñar y construir la interfaz de usuario consumiendo la API REST del backend. Describe las convenciones, autenticación y manejo de errores esperados.

## 2. Base URL
En el entorno de desarrollo local, la URL base esperada de la API es:
- `http://localhost:8000/api` (usando `php artisan serve`)
- o `http://dentistawebpg.test/api` (si se usa Laragon u otro entorno local virtual)

**Regla:** Todas las rutas expuestas por el backend requieren estar prefijadas con `/api`. Utilizar `APP_URL` en las variables de entorno del frontend.

## 3. Autenticación
El sistema utiliza **Laravel Sanctum** basado en tokens.
- **Login:** Se efectúa mediante credenciales (`correoElectronico` y `password`), retorna un Token.
- **Token Bearer:** Todas las rutas privadas exigen que el token sea enviado en los headers.
- **Headers requeridos** en cada petición protegida:
```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```
- **Logout:** Invalida y elimina el token de la base de datos.
- **Expiración:** Los tokens tienen una expiración predeterminada en el backend (ej. 8 horas).
- **Manejo 401:** Si el backend responde `401 Unauthorized`, el frontend debe eliminar el token almacenado localmente e inmediatamente redirigir a la pantalla de Login.
- **Manejo 403:** Si responde `403 Forbidden`, la sesión es válida pero el rol del empleado no tiene permisos para esa acción (ej. un Dentista tratando de cobrar).

## 4. Códigos HTTP usados
| Código | Significado para frontend |
|---|---|
| **200** | Consulta (GET) o actualización (PUT/PATCH) completada correctamente. |
| **201** | Creación (POST) correcta de un recurso. |
| **204** | Eliminación/Baja lógica (DELETE) o cancelación sin contenido en la respuesta. |
| **401** | Sesión inactiva, token expirado, token inválido o no proporcionado. |
| **403** | Autenticado pero sin los permisos/rol necesarios. |
| **404** | Recurso no existe o está inactivo (dado de baja). |
| **422** | Error de validación de formulario o regla de negocio rota (ej. pago incompleto, agenda ocupada). |

## 5. Convenciones generales
- **Eliminación Lógica:** La inmensa mayoría de peticiones `DELETE` ejecutan una baja lógica (`estado=false`). No asumir eliminación física real.
- **Inactivos Ocultos:** Si el frontend consulta un `id` que está inactivo, el backend puede devolver `404 Not Found`.
- **JSON estricto:** Tanto las respuestas (resources) como los envíos (bodys) manejan formato JSON.
- **Errores de validación:** Los errores HTTP 422 devuelven un JSON de estructura: `{ "message": "...", "errors": { "campo": ["Error 1"] } }`.
- **Campos controlados:** El frontend **NUNCA** debe enviar campos derivados por backend como `idCorte`, `idEmpleado` (cuando se trata del usuario actual), o `pagado`.
- **Campos con `ñ`:** Deben respetarse los nombres de variable/key en JSON si están así en el backend (ej. `contraseña` si así lo espera, aunque se recomienda verificar los DTOs).

## 6. Flujo mínimo de sesión frontend
1. Enviar credenciales a `/login`.
2. Guardar el `{token}` retornado (ej. en LocalStorage, SessionStorage o Cookies seguras).
3. Adjuntar el token como `Bearer` en los interceptors de HTTP (Axios / Fetch).
4. Si cualquier petición recibe `401`, borrar el token del storage y forzar redirección a `/login`.
5. Enviar POST a `/logout` al pulsar "Cerrar sesión".
6. Controlar la expiración reactivamente interceptando errores de Axios.

---

## 7. Módulo: Autenticación y seguridad

- **POST `/login`**
  - Rol requerido: Ninguno (Público).
  - Body: `{ "correoElectronico": "admin@...", "password": "..." }`
  - Respuesta 200: `{ "token": "...", "empleado": {...} }`
  - Errores comunes: 401 (Credenciales incorrectas o cuenta inactiva), 422 (Falta correo).

- **GET `/me`**
  - Rol requerido: Cualquier empleado activo.
  - Body: Vacío.
  - Respuesta 200: Datos del empleado autenticado.

- **POST `/logout`**
  - Rol requerido: Cualquier empleado activo.
  - Body: Vacío.
  - Respuesta 200/204: Confirmación.

- **POST `/change-password`**
  - Rol requerido: Cualquier empleado activo.
  - Body: `{ "current_password": "...", "new_password": "...", "new_password_confirmation": "..." }`
  - Respuesta 200: Éxito.

---

## 8. Módulo: Pacientes/personas

- **GET `/personas`**
  - Búsqueda: Soporta `?search=Nombre`. Solo devuelve activos.
- **POST `/personas`**
  - Body esperado: `nombre`, `apellidoP`, `apellidoM`, `celular`, `correoElectronico` (único, nullable).
  - Validación: Correo repetido devuelve 422.
- **GET `/personas/{id}`**
  - Inactivos devuelven 404.
- **PUT/PATCH `/personas/{id}`**
  - Si se intenta editar inactivo, devuelve 404.
- **DELETE `/personas/{id}`**
  - Baja lógica; un segundo DELETE da 404.
- *Permisos:* Admin y recepcionista pueden gestionar. Dentista bloqueado para crear.

---

## 9. Módulo: Pagos y cortes

### Pagos
- **POST `/pagos`**
  - El frontend **NO** envía: `idEmpleado` (lo toma de auth), `idCorte` (usa el corte activo), `pagado`.
  - El frontend **DEBE enviar**: `total`, `efectivo`, `tarjeta`, y asociar a citas/tratamientos si así lo requiere la API.
  - Regla: `total = efectivo + tarjeta`. No hay pagos parciales (422 si no cuadra).
- **PUT/PATCH `/pagos/{id}`**
  - Pagos de corte cerrado no se editan (422).

### Cortes
- **GET `/cortes`** | **GET `/cortes/{id}`**
- **GET `/cortes/activo`** (si existe, para ver el corte del turno actual).
- **POST `/cortes`**
  - Abre un corte. Sólo puede existir **un corte activo**. 422 si ya hay uno abierto.
- **PUT/PATCH `/cortes/{id}`**
  - Cierra el corte calculando totales automáticos. El frontend no controla `tEfectivo`/`tTarjeta`. Corte cerrado es inmutable.

---

## 10. Módulo: Comprobantes internos

- **GET `/comprobantes`**
- **POST `/comprobantes`**
  - Se emite DESDE un pago. Body: `{ "idPago": 1, "observaciones": "..." }`.
  - El backend genera el `folio`. Los montos se "congelan" (snapshot del pago).
  - 422 si se manda importe desde el front, o si el pago ya tiene recibo.
  - Es recibo interno, NO CFDI.
- **GET `/comprobantes/{id}`**
- **DELETE `/comprobantes/{id}`**
  - Cancelación lógica. No cancela el pago, solo el recibo.

---

## 11. Módulo: Inventario

### Productos de inventario
- **GET `/inventario/productos`** | **GET `/inventario/productos/{id}`**
  - Producto inactivo = 404. Devuelve variable `bajoStock` (booleano).
- **POST `/inventario/productos`**
  - Body: `nombre`, `unidadMedida`, `stockInicial`.
- **PUT/PATCH `/inventario/productos/{id}`**
  - No editar `stockActual` directamente aquí.
- **DELETE `/inventario/productos/{id}`** (Baja lógica).

### Movimientos de inventario
- **GET `/inventario/movimientos`**
- **POST `/inventario/movimientos`**
  - Body: `idProductoInventario`, `tipoMovimiento` ("entrada", "salida", "ajuste"), `cantidad`, `motivo`.
  - "salida" resta. Si el stock queda negativo, backend tira 422.
  - "ajuste" sobreescribe stock físico (cantidad = stock real total).
  - Backend controla `idEmpleado`, `stockAnterior` y `stockNuevo`.

---

## 12. Módulo: Citas/agenda

- Rutas: GET, POST, PUT, DELETE a `/citas`.
- Frontend **DEBE enviar** `idEmpleado` (para saber a qué dentista se asigna).
- Validación de traslapes: Backend devolverá 422 si hay choque de agenda (analiza la duración del servicio seleccionado).
- Históricos: `dentista` puede venir `null` en registros antiguos.
- Respetar toda lógica de negocio existente implementada por otros desarrolladores.

---

## 13. Módulo: Empleados/usuarios

- Operaciones CRUD clásicas en `/empleados`.
- Roles manejados: admin, recepcionista, dentista.
- Inactivos no podrán acceder mediante `/login`.

---

## 14. Módulo: Servicios

- Operaciones CRUD en `/servicios`.
- Entidad que define la duración de la cita y costos base.
- Si vienen inactivos o estados particulares, ocultar del selector del frontend.

---

## 15. Módulo: Recetas

- Lista de endpoints para prescribir.
- Roles: Médicos (Dentistas) y Admin.

---

## 16. Catálogos

- Uso en frontend: Llenar selects e inputs en UI.
- Rutas típicas: Tipos de Empleado, Clases de Servicio.
- Permisos: Solo lectura.

---

## 17. Ejemplos de flujo frontend

### Flujo login
1. HTTP POST `/api/login` -> Recibe `{ token }`.
2. Guardar token en storage de estado global (Zustand, Pinia, Redux).
3. HTTP GET `/api/me` (con Bearer token) para pintar avatar y nombre en Header.

### Flujo crear paciente
1. Usuario llena modal de registro.
2. HTTP POST `/api/personas`. Backend responde 201 Created.
3. Se recarga la tabla local: HTTP GET `/api/personas`.

### Flujo caja (Turno completo)
1. Cajero llega: HTTP POST `/api/cortes` (abrir corte).
2. Viene paciente: HTTP POST `/api/pagos` (total $500, efectivo $500).
3. Emitir recibo: HTTP POST `/api/comprobantes` { idPago: X }.
4. Cajero se va: HTTP PUT `/api/cortes/{id}` para cerrar.

### Flujo inventario
1. Crear producto Gasa: POST `/inventario/productos`.
2. Llega almacén: POST `/inventario/movimientos` (tipo: entrada, cant: 50).
3. Se usan 5 gasas: POST `/inventario/movimientos` (tipo: salida, cant: 5).
4. Chequeo mensual: POST `/inventario/movimientos` (tipo: ajuste, cant: 40 físicas).

### Flujo agenda
1. Se listan dentistas activos.
2. Cajero pulsa horario 10:00 AM para Dr. X.
3. POST `/citas` { idEmpleado: X, fecha...}.
4. Si responde 422 ("Horario traslapado"), mostrar toast de error.

---

## 18. Manejo de errores en frontend

Para garantizar la mejor Experiencia de Usuario (UX):
- **HTTP 401:** Disparar logout local. No mostrar modal de error intrusivo, redirigir suavemente.
- **HTTP 403:** Mostrar Toast naranja: "No tienes los permisos necesarios."
- **HTTP 404:** Redirigir a vista "Elemento no encontrado" o Toast si fue acción aislada.
- **HTTP 422:** Parsear `error.response.data.errors` y pintar los textos rojos directamente debajo de cada input del formulario afectado.
- **HTTP 500+:** Mostrar error modal o Toast rojo "Error interno de servidor" y registrar métrica.

---

## 19. Consideraciones para implementar frontend con otra IA

Si un Agente o IA se encarga del Frontend, utilizar esta sección como Prompt estructurado:

**Arquitectura recomendada:**
- **Cliente API:** Usar un envoltorio de Axios o Fetch (Interceptor) para inyectar token automáticamente y gestionar 401 globales.
- **Gestor de estado (Auth):** Guardar sesión actual de manera persistente pero segura.
- **Guards / Middleware:** Proteger rutas de UI basado en `rol`. Dentistas no ven Caja.
- **Componentes comunes clave:**
  1. Componente Tabla genérica con paginación/búsqueda.
  2. Formulario inyectable con inyección reactiva de errores 422.
  3. Modal de confirmación para acciones `DELETE`.
  
**Orden Sugerido de Implementación:**
1. Setup base (Rutas, cliente HTTP, Layouts).
2. Login y Estado de Sesión global (Auth).
3. Módulo Personas (Básico).
4. Módulo Agenda (Componente Calendario es complejo, dejar estructura clara).
5. Módulo Caja (Con lógica dependiente de pago total).
6. Módulo Inventario.

**Riesgos de integración:**
1. Evitar crear dependencias circulares al querer vincular manualmente citas e inventario (el backend no lo hace).
2. Prestar atención a los formatos de fecha esperados (Y-m-d H:i:s).

---

## 20. Pendientes fuera de alcance
- **Facturación fiscal SAT:** No está mapeada; el comprobante actual es interno.
- **Generación PDF:** El frontend deberá generarlo vía canvas o requerir la implementación futura en el backend.
- **Correos:** No se han configurado endpoints que disparen emails (notificaciones/recibos).
- **Consumo automático de inventario:** Cuando se haga una cita, NO se rebajan guantes automáticamente; se debe hacer la "salida" manualmente.
- **Deploy:** Faltan las configuraciones puramente de infraestructura (CORS, SSL, dominios).
