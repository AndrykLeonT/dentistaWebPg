# Guía de Integración Frontend — Vue 3

> Fecha: 2026-04-28  
> Backend: Laravel 10 + Sanctum  
> Frontend recomendado: Vue 3 + Pinia + Axios

---

## Plan de acción — pasos en orden

```
1. Configurar cliente HTTP (Axios)         ← base URL, interceptores
2. Implementar flujo de autenticación      ← login → token → store Pinia
3. Redirigir si requiresPasswordChange     ← pantalla cambio de contraseña
4. Leer rol del usuario y restringir UI    ← navegación, botones, rutas
5. Implementar módulos por pantalla        ← en el orden del flujo clínico
   a. Servicios y ClaseServicio (catálogos)
   b. Pacientes
   c. Citas
   d. Recetas
   e. Empleados (solo admin)
   f. Pagos
   g. Cortes de caja
6. Manejo global de errores                ← 401, 403, 422, 500
```

---

## 1. Configurar cliente HTTP

Crea `src/services/api.js`:

```js
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

// Inyecta el token en cada request
api.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) config.headers.Authorization = `Bearer ${auth.token}`
  return config
})

// Manejo global de errores
api.interceptors.response.use(
  (res) => res,
  (error) => {
    const status = error.response?.status
    if (status === 401) {
      useAuthStore().logout()
      router.push('/login')
    }
    if (status === 403) {
      router.push('/forbidden')
    }
    return Promise.reject(error)
  }
)

export default api
```

En `.env` del frontend:
```env
VITE_API_URL=http://localhost:8000/api
```

---

## 2. Store de autenticación (Pinia)

Crea `src/stores/auth.js`:

```js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const token    = ref(localStorage.getItem('token') ?? null)
  const empleado = ref(JSON.parse(localStorage.getItem('empleado') ?? 'null'))

  const isAuthenticated = computed(() => !!token.value)
  const rol = computed(() => empleado.value?.tipoEmpleado?.nombre?.toLowerCase() ?? '')
  const isAdmin       = computed(() => rol.value === 'administrador')
  const isDentista    = computed(() => rol.value === 'dentista')
  const isRecepcionista = computed(() => rol.value === 'recepcionista')

  async function login(usuario, contraseña) {
    const { data } = await api.post('/login', { usuario, contraseña })
    token.value    = data.token
    empleado.value = data.empleado
    localStorage.setItem('token',    data.token)
    localStorage.setItem('empleado', JSON.stringify(data.empleado))
    return data  // incluye requiresPasswordChange
  }

  async function logout() {
    try { await api.post('/logout') } catch (_) {}
    token.value    = null
    empleado.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('empleado')
  }

  async function fetchMe() {
    const { data } = await api.get('/me')
    empleado.value = data.data
    localStorage.setItem('empleado', JSON.stringify(data.data))
  }

  return { token, empleado, isAuthenticated, rol, isAdmin, isDentista, isRecepcionista,
           login, logout, fetchMe }
})
```

---

## 3. Flujo de autenticación completo

### Login page

```js
// LoginView.vue (script setup)
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()
const error  = ref(null)

async function handleLogin(usuario, contraseña) {
  try {
    const data = await auth.login(usuario, contraseña)

    if (data.requiresPasswordChange) {
      router.push('/change-password')  // pantalla obligatoria
    } else {
      router.push('/dashboard')
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al iniciar sesión'
  }
}
```

### Pantalla cambio de contraseña obligatorio

```js
// Llama POST /api/change-password
async function handleChangePassword(contraseñaActual, nuevaContraseña, nuevaContraseña_confirmation) {
  await api.post('/change-password', {
    contraseñaActual,
    nuevaContraseña,
    nuevaContraseña_confirmation,  // Laravel confirma que coincidan
  })
  router.push('/dashboard')
}
```

### Guard de rutas (Vue Router)

```js
// router/index.js
router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login'
  if (to.meta.requiresAdmin && !auth.isAdmin) return '/forbidden'
  if (to.meta.requiresAdminOrDentista && !(auth.isAdmin || auth.isDentista)) return '/forbidden'
})
```

Configuración de rutas ejemplo:
```js
{ path: '/empleados', meta: { requiresAuth: true, requiresAdmin: true } },
{ path: '/recetas',   meta: { requiresAuth: true, requiresAdminOrDentista: true } },
{ path: '/citas',     meta: { requiresAuth: true } },
```

---

## 4. Respuesta del login y estructura del empleado

`POST /api/login`  
**Request body:**
```json
{ "usuario": "jdoe", "contraseña": "secret123" }
```

**Response 200:**
```json
{
  "token": "1|abc123...",
  "requiresPasswordChange": false,
  "empleado": {
    "id": 1,
    "usuario": "jdoe",
    "rfc": "DOEJ800101ABC",
    "tipoEmpleado": {
      "id": 2,
      "nombre": "Recepcionista"
    },
    "persona": {
      "id": 5,
      "nombreCompleto": "Juan Doe Martínez",
      "celular": "6121234567",
      "correoElectronico": "jdoe@clinica.com"
    }
  }
}
```

> El campo `tipoEmpleado.nombre` puede ser: `"Administrador"`, `"Dentista"` o `"Recepcionista"`.  
> Úsalo para control de acceso en la UI.

**Response 401 — credenciales incorrectas:**
```json
{ "message": "Credenciales incorrectas." }
```

---

## 5. Módulos — endpoints y formas de respuesta

### 5.1 Pacientes (`/api/personas`)

**Permisos:**
- Leer: todos los roles
- Crear/Editar: Admin, Recepcionista
- Eliminar: Solo Admin

**GET /api/personas**
```
Query params opcionales:
  ?search=texto     → busca en nombre, apellidoP, apellidoM
```
Respuesta:
```json
{
  "data": [
    {
      "id": 5,
      "nombreCompleto": "María García López",
      "nombre": "María",
      "apellidoP": "García",
      "apellidoM": "López",
      "celular": "6121234567",
      "correoElectronico": "maria@ejemplo.com",
      "fechaRegistro": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

**POST /api/personas**
```json
{
  "nombre": "María",
  "apellidoP": "García",
  "apellidoM": "López",
  "celular": "6121234567",
  "correoElectronico": "maria@ejemplo.com"
}
```

**PUT /api/personas/{id}** — mismos campos, todos opcionales  
**DELETE /api/personas/{id}** — desactiva (`estado=0`), responde `204 No Content`

---

### 5.2 Servicios y Categorías

**GET /api/servicios** — sin auth
Respuesta:
```json
{
  "data": [
    {
      "id": 1,
      "nombre": "Limpieza dental",
      "costo": "450.00",
      "duracion": "00:45:00",
      "claseServicio": { "id": 2, "nombre": "Preventivo" }
    }
  ]
}
```

**GET /api/clases-servicio** — lista de categorías  
```json
{ "data": [{ "id": 1, "nombre": "Preventivo" }, { "id": 2, "nombre": "Ortodoncia" }] }
```

**POST /api/servicios** (Admin/Recepcionista)
```json
{
  "idClaseServicio": 1,
  "nombre": "Limpieza dental",
  "costo": 450.00,
  "duracion": "00:45:00"
}
```

---

### 5.3 Citas (`/api/citas`)

**Permisos:**
- Leer: todos los roles
- Crear/Editar: Admin, Recepcionista
- Eliminar: Solo Admin

**GET /api/citas**
```
Query params opcionales:
  ?fecha=2024-08-15       → filtrar por día
  ?paciente_id=5          → filtrar por paciente
  ?servicio_id=2          → filtrar por servicio
  (se pueden combinar)
```

Respuesta:
```json
{
  "data": [
    {
      "id": 12,
      "fechaRegistro": "2024-07-01T09:00:00.000000Z",
      "fechaProgramada": "2024-08-15",
      "hora": "10:30:00",
      "duracion": null,
      "motivo": "Revisión de bracket",
      "paciente": { "id": 5, "nombreCompleto": "María García López" },
      "servicio": { "id": 2, "nombre": "Revisión ortodoncia", "costo": "350.00" },
      "receta": null
    }
  ]
}
```

**POST /api/citas**
```json
{
  "idPersona": 5,
  "idServicio": 2,
  "fechaProgramada": "2024-08-15",
  "hora": "10:30",
  "motivo": "Revisión de bracket"
}
```
> Error de colisión (422):  
> `{ "errors": { "hora": ["Ya existe una cita para ese servicio en esa fecha y hora."] } }`

---

### 5.4 Recetas (`/api/recetas`)

**Permisos:** Admin y Dentista únicamente

**GET /api/recetas** — lista todas las recetas  
**GET /api/recetas/{id}** — ver receta específica

Respuesta:
```json
{
  "data": {
    "id": 3,
    "indicaciones": "Tomar ibuprofeno 400mg cada 8 horas por 3 días",
    "cita": {
      "id": 12,
      "fechaProgramada": "2024-08-15",
      "hora": "10:30:00",
      "paciente": { "id": 5, "nombreCompleto": "María García López" }
    }
  }
}
```

**POST /api/recetas** (Dentista/Admin)
```json
{
  "idCita": 12,
  "indicaciones": "Tomar ibuprofeno 400mg cada 8 horas por 3 días"
}
```
> Solo puede haber una receta por cita. Si ya existe, responde `422`:  
> `{ "errors": { "idCita": ["Esta cita ya tiene una receta asignada."] } }`

---

### 5.5 Empleados (`/api/empleados`)

**Permisos:**
- Leer: todos los roles autenticados
- CRUD completo: Solo Admin

**GET /api/empleados**
```json
{
  "data": [
    {
      "id": 1,
      "usuario": "jdoe",
      "rfc": "DOEJ800101ABC",
      "tipoEmpleado": { "id": 2, "nombre": "Recepcionista" },
      "persona": {
        "id": 5,
        "nombreCompleto": "Juan Doe Martínez",
        "celular": "6121234567",
        "correoElectronico": "jdoe@clinica.com"
      }
    }
  ]
}
```

**POST /api/empleados** (Solo Admin)
```json
{
  "nombre": "Luis",
  "apellidoP": "Martínez",
  "apellidoM": "Soto",
  "celular": "6127654321",
  "correoElectronico": "luis@clinica.com",
  "idTipoEmpleado": 2,
  "usuario": "lmartinez",
  "rfc": "MASL800101ABC",
  "contraseña": "password_inicial",
  "palabraClave": "palabra_secreta"
}
```

**POST /api/empleados/{id}/reset-password** (Solo Admin)
```json
{
  "nuevaContraseña": "nueva_temporal_123",
  "nuevaContraseña_confirmation": "nueva_temporal_123"
}
```
> Activa `requiresPasswordChange=true` para ese empleado. En su próximo login será redirigido.

---

### 5.6 Cortes de caja (`/api/cortes`)

**Permisos:** Admin y Recepcionista

**Flujo típico:**
1. Verificar si hay corte abierto → `GET /api/cortes/activo`
2. Si no hay → abrir corte → `POST /api/cortes`
3. Registrar pagos durante el turno → `POST /api/pagos`
4. Al cierre → cerrar corte → `PUT /api/cortes/{id}`

**GET /api/cortes/activo**
```json
{
  "data": {
    "id": 7,
    "fechaInicio": "2024-08-15T08:00:00.000000Z",
    "fechaFin": null,
    "fDeCaja": "500.00",
    "tEfectivo": "0.00",
    "tTarjeta": "0.00",
    "totalRecaudado": 0,
    "correcto": null,
    "pagos": []
  }
}
```
> Responde `404` si no hay corte abierto. Úsalo para habilitar/deshabilitar el botón "Registrar pago".

**POST /api/cortes** — abrir corte
```json
{ "fDeCaja": 500.00 }
```
> `fechaInicio` se asigna automáticamente con la hora del servidor.  
> Error si ya existe un corte abierto (422):  
> `{ "message": "Ya existe un corte de caja abierto. Ciérralo antes de abrir uno nuevo." }`

**PUT /api/cortes/{id}** — cerrar corte
```json
{ "fechaFin": "2024-08-15 20:00:00" }
```
Respuesta tras cerrar:
```json
{
  "data": {
    "id": 7,
    "fechaInicio": "2024-08-15T08:00:00.000000Z",
    "fechaFin": "2024-08-15T20:00:00.000000Z",
    "fDeCaja": "500.00",
    "tEfectivo": "1200.00",
    "tTarjeta": "850.00",
    "totalRecaudado": 2050
  }
}
```
> `tEfectivo` y `tTarjeta` se calculan sumando automáticamente todos los pagos del período.

---

### 5.7 Pagos (`/api/pagos`)

**Permisos:** Admin y Recepcionista

**GET /api/pagos**
```json
{
  "data": [
    {
      "id": 15,
      "total": "500.00",
      "efectivo": "200.00",
      "tarjeta": "300.00",
      "pendiente": 0,
      "pagado": true,
      "fechaRegistro": "2024-08-15T10:35:00.000000Z",
      "paciente": { "id": 5, "nombreCompleto": "María García López" },
      "empleado": { "id": 1, "usuario": "jdoe", "nombre": "Juan Doe" },
      "corte": { "id": 7, "fechaInicio": "2024-08-15T08:00:00.000000Z", "fechaFin": null }
    }
  ]
}
```

**POST /api/pagos**
```json
{
  "idPersona": 5,
  "total": 500.00,
  "efectivo": 200.00,
  "tarjeta": 300.00
}
```
> `idEmpleado` e `idCorte` se asignan automáticamente desde el token y el corte abierto.  
> Error si no hay corte abierto (422):  
> `{ "message": "No hay un corte de caja abierto. Abre un corte antes de registrar pagos." }`

---

## 6. Manejo de errores en Vue

```js
// composable: src/composables/useApiError.js
export function useApiError() {
  const fieldErrors = ref({})   // errores de validación por campo (422)
  const globalError = ref(null) // mensaje general

  function handleError(error) {
    fieldErrors.value = {}
    globalError.value = null

    const status = error.response?.status
    const data   = error.response?.data

    if (status === 422) {
      fieldErrors.value = data.errors ?? {}
      globalError.value = data.message ?? 'Error de validación'
    } else if (status === 403) {
      globalError.value = 'No tienes permiso para realizar esta acción'
    } else if (status === 404) {
      globalError.value = 'Registro no encontrado'
    } else {
      globalError.value = data?.message ?? 'Error inesperado'
    }
  }

  function clearErrors() {
    fieldErrors.value = {}
    globalError.value = null
  }

  return { fieldErrors, globalError, handleError, clearErrors }
}
```

Uso en un componente:
```vue
<script setup>
const { fieldErrors, globalError, handleError } = useApiError()

async function guardar() {
  try {
    await api.post('/personas', form.value)
    router.push('/pacientes')
  } catch (e) {
    handleError(e)
  }
}
</script>

<template>
  <p v-if="globalError" class="text-red-600">{{ globalError }}</p>
  <input v-model="form.nombre" />
  <p v-if="fieldErrors.nombre">{{ fieldErrors.nombre[0] }}</p>
</template>
```

---

## 7. Tabla de permisos por pantalla

| Pantalla / Módulo        | Admin | Dentista | Recepcionista |
|--------------------------|-------|----------|---------------|
| Login                    | ✅    | ✅       | ✅            |
| Dashboard                | ✅    | ✅       | ✅            |
| Pacientes (ver)          | ✅    | ✅       | ✅            |
| Pacientes (crear/editar) | ✅    | ❌       | ✅            |
| Citas (ver)              | ✅    | ✅       | ✅            |
| Citas (crear/editar)     | ✅    | ❌       | ✅            |
| Recetas                  | ✅    | ✅       | ❌            |
| Servicios (ver)          | ✅    | ✅       | ✅            |
| Servicios (editar)       | ✅    | ❌       | ✅            |
| Empleados (ver)          | ✅    | ✅       | ✅            |
| Empleados (CRUD)         | ✅    | ❌       | ❌            |
| Pagos                    | ✅    | ❌       | ✅            |
| Cortes de caja           | ✅    | ❌       | ✅            |

---

## 8. Estructura de servicios sugerida para Vue

```
src/
  services/
    api.js          ← instancia Axios (interceptores)
    auth.js         ← login(), logout(), changePassword()
    personas.js     ← getAll(), getById(), create(), update(), remove()
    citas.js        ← getAll(filters), getById(), create(), update(), cancel()
    recetas.js      ← getAll(), getById(), create(), update()
    servicios.js    ← getAll(), getById(), create(), update()
    empleados.js    ← getAll(), getById(), create(), update(), resetPassword()
    pagos.js        ← getAll(), getById(), create()
    cortes.js       ← getAll(), getActivo(), create(), close()
  stores/
    auth.js         ← token, empleado, rol, login(), logout()
  composables/
    useApiError.js  ← handleError(), fieldErrors, globalError
```

Ejemplo de servicio:
```js
// src/services/personas.js
import api from './api'

export const personasService = {
  getAll: (search) => api.get('/personas', { params: { search } }),
  getById: (id) => api.get(`/personas/${id}`),
  create: (data) => api.post('/personas', data),
  update: (id, data) => api.put(`/personas/${id}`, data),
  remove: (id) => api.delete(`/personas/${id}`),
}
```

---

## 9. Variables de entorno necesarias

En el proyecto Vue (`.env`):
```env
VITE_API_URL=http://localhost:8000/api
```

En el backend Laravel (`.env`):
```env
FRONTEND_URL=http://localhost:5173
```
> El backend ya tiene CORS configurado para leer `FRONTEND_URL`. Solo cambia estas dos líneas al desplegar.
