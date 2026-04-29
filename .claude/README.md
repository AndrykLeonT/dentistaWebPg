# .claude/ — Índice de documentación del agente

Este directorio contiene la documentación estructurada del proyecto para Claude Code.
Leer este índice antes de cualquier tarea para saber qué archivo consultar.

## Archivos en este directorio

| Archivo | Contenido |
|---|---|
| `architecture.md` | Visión general del sistema, stack, separación de repos |
| `domain-model.md` | Modelo de dominio completo: entidades, relaciones, reglas de negocio |
| `api-contracts.md` | Contratos de la API REST: endpoints, payloads, respuestas |
| `roles-and-permissions.md` | Roles (Admin, Dentista, Recepcionista), sus permisos y restricciones |
| `dev-conventions.md` | Convenciones de código Laravel: PK no estándar, naming, patrones a seguir |
| `roadmap.md` | Fases de desarrollo priorizadas y estado actual de cada módulo |

## Regla de oro para el agente

> Antes de implementar cualquier feature, leer `domain-model.md` y `dev-conventions.md`.
> Antes de crear un endpoint, leer `api-contracts.md` y `roles-and-permissions.md`.
> Antes de planear trabajo, leer `roadmap.md`.
