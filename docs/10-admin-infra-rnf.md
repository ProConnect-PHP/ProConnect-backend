# 10 — Administración, Infraestructura y Requerimientos No Funcionales

## Alcance

Este documento agrupa administración, monitoreo, Docker, CI/CD, despliegue, PWA y requerimientos no funcionales asociados a ProConnect.

> Estado general: Docker, API REST, frontend/backend desacoplados, queues, pagos, mapa, reseñas y emails están contemplados. CI/CD, cloud HTTPS, WebSockets, LiveKit, OAuth y PWA quedan pendientes o requieren validación.

---

## CU27 — Panel Administrativo

### Estado

- Estado funcional: 🟡 Parcial / pendiente de validación.
- Estado documental: 📝 Agregado.
- Prioridad: Media.

### Actor principal

Administrador.

### Objetivo

Permitir supervisar usuarios, profesionales, servicios, reservas, pagos y actividad general del sistema.

### Funciones esperadas

- Listado de usuarios.
- Gestión de profesionales.
- Visualización de servicios.
- Visualización de reservas.
- Visualización de pagos.
- Moderación de reseñas.
- Métricas generales.
- Estado de jobs/colas.
- Monitoreo básico de actividad.

### Reglas de negocio

- Solo administradores pueden acceder.
- Toda acción crítica debe auditarse.
- El panel debe respetar aislamiento lógico de tenants/profesionales.
- No debe exponer datos sensibles innecesarios.

### Pendientes

- 🔒 Validar alcance real implementado.
- 🟡 Documentar roles/permisos.
- 🟡 Documentar métricas disponibles.

---

## CU29 — Ejecutar Sistema Dockerizado

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: 📝 Agregado.
- Prioridad: Alta.

### Objetivo

Permitir ejecutar el sistema completo mediante contenedores.

### Componentes esperados

- Laravel API.
- Frontend SPA.
- PostgreSQL.
- Redis.
- Queue worker.
- Scheduler.
- Nginx, si aplica.

### Reglas técnicas

- Variables sensibles en `.env`.
- No versionar secretos.
- PostgreSQL con volumen persistente.
- Redis disponible para queues, locks y cache.
- Workers separados del proceso HTTP.
- Scheduler separado o ejecutado mediante contenedor dedicado.

### Comandos conceptuales

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:work
./vendor/bin/sail artisan horizon
```

### Pendientes

- 🟡 Documentar comandos finales exactos.
- 🟡 Documentar troubleshooting.
- 🔒 Validar compose definitivo.

---

## CU31 — CI/CD

### Estado

- Estado funcional: ❌ Pendiente.
- Estado documental: 📝 Agregado.
- Prioridad: Media/Alta.

### Objetivo

Automatizar validación, build y despliegue del sistema.

### Pipeline esperado

- Checkout.
- Instalación de dependencias backend.
- Instalación de dependencias frontend.
- Lint frontend.
- Tests frontend.
- Tests backend.
- Build frontend.
- Validación Docker.
- Deploy si corresponde.

### Reglas técnicas

- No exponer secretos.
- Usar GitHub Actions.
- Separar jobs por frontend/backend si están desacoplados.
- Bloquear deploy si fallan tests críticos.

### Pendientes

- ❌ Crear workflows.
- ❌ Configurar secrets.
- ❌ Definir ambientes.
- ❌ Documentar estrategia de deploy.

---

## CU32 — Publicación Cloud con HTTPS

### Estado

- Estado funcional: ❌ Pendiente.
- Estado documental: 📝 Agregado.
- Prioridad: Media.

### Objetivo

Publicar la plataforma en un entorno accesible por HTTPS.

### Componentes esperados

- VPS o proveedor cloud.
- Docker Compose o equivalente.
- Nginx reverse proxy.
- Certificados SSL.
- PostgreSQL persistente.
- Redis.
- Workers.
- Scheduler.

### Pendientes

- ❌ Definir proveedor.
- ❌ Configurar dominio.
- ❌ Configurar HTTPS.
- ❌ Configurar despliegue.
- ❌ Documentar runbook.

---

## CU33 — PWA

### Estado

- Estado funcional: ❌ Pendiente / opcional.
- Estado documental: 📝 Agregado.
- Prioridad: Baja si no da el tiempo.

### Objetivo

Transformar el frontend en Progressive Web App para mejorar experiencia móvil.

### Funciones posibles

- Manifest.
- Service worker.
- Instalación en dispositivo.
- Cache básico.
- Offline parcial.
- Push notifications futuras.

### Pendientes

- ❌ Implementar PWA.
- ❌ Definir si entra en alcance final.

---

# Requerimientos No Funcionales

## Obligatorios

| RNF | Estado | Observación |
|---|---:|---|
| Diseño responsivo | ✅ | TailwindCSS / SPA responsive. |
| API REST | ✅ | Backend Laravel API REST. |
| Autenticación y autorización | 🟡 | Auth contemplada; autorización requiere validación. |
| Notificaciones por email | ✅ | Implementadas mediante jobs/listeners. |
| Visualización geográfica en mapa | ✅ | Mapbox integrado. |
| Sistema de reseñas | ✅ | Implementado. |
| Videollamadas integradas | ❌ | LiveKit pendiente. |
| Interacción realtime con WebSockets | ❌ | Laravel Echo/WebSockets pendiente. |

## Electivos

| RNF | Estado | Puntos | Observación |
|---|---:|---:|---|
| Integración con pasarela de pago | ✅ | 2 | Flujo de pagos contemplado. |
| Control de concurrencia en reservas | ✅ | 3 | Requiere documentar lock exacto. |
| Recordatorios automáticos | 🟡 | 2 | Backend/jobs posibles; falta validar frontend. |
| Dockerización del sistema | ✅ | 2 | Proyecto dockerizado. |
| CI/CD | ❌ | 3 | Pendiente. |
| Colas para tareas asincrónicas | ✅ | 3 | Redis queues/jobs/Horizon. |
| Base NoSQL para logs | ❌ | 1 | Pendiente. |
| OAuth redes sociales | ❌ | 2 | Pendiente/no validado. |
| Publicación cloud HTTPS | ❌ | 2 | Pendiente. |
| PWA | ❌ | 3 | Pendiente/opcional. |
| Arquitectura desacoplada frontend/backend | ✅ | 4 | Frontend separado de API. |

---

# Pendientes críticos

1. Implementar LiveKit real.
2. Implementar Laravel Echo/WebSockets.
3. Completar OAuth si se mantiene como electivo.
4. Crear CI/CD.
5. Publicar en cloud HTTPS si se decide llegar a producción/demo pública.
6. Documentar endpoints principales.
7. Documentar estrategia de concurrencia.
8. Corregir/verificar links de emails.
9. Validar políticas de cancelación y devolución de paquetes.
