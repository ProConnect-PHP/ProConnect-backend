# CU09 — Crear Servicio

## Descripción

Permite a un profesional publicar un nuevo servicio dentro de la plataforma, definiendo sus características, modalidad de atención, duración, precio y configuración general.

Los servicios creados podrán posteriormente ser reservados por clientes, formar parte de paquetes y utilizar las reglas de disponibilidad configuradas por el profesional.

---

## Actores

* Profesional

---

## Precondiciones

* El actor debe encontrarse autenticado.
* El actor debe poseer un perfil profesional activo.
* El actor debe contar con permisos para crear servicios.
* En caso de asociar una empresa, ésta debe pertenecer al profesional.

---

## Disparador

El actor selecciona la opción **"Crear Servicio"** desde su panel profesional.

---

## Flujo Principal

1. El sistema muestra el formulario de creación de servicios.
2. El actor completa los datos generales del servicio.
3. El actor selecciona la modalidad de atención.
4. El actor configura la duración, precio y restricciones del servicio.
5. Opcionalmente, el actor asocia una empresa de su propiedad.
6. El actor confirma la creación del servicio.
7. El sistema valida los datos ingresados.
8. El sistema verifica que el actor posea un perfil profesional válido.
9. El sistema verifica que la empresa asociada pertenezca al profesional.
10. El sistema registra el nuevo servicio.
11. El sistema registra el evento de creación en la bitácora de actividad.
12. El sistema informa que el servicio fue creado correctamente.

---

## Flujos Alternativos

### A1 — Usuario sin perfil profesional

En el paso 8:

1. El sistema detecta que el usuario no posee un perfil profesional.
2. El sistema rechaza la operación.
3. El sistema informa que es necesario contar con un perfil profesional para crear servicios.

**Resultado:** El servicio no es creado.

---

### A2 — Empresa no autorizada

En el paso 9:

1. El sistema detecta que la empresa seleccionada no pertenece al profesional.
2. El sistema rechaza la operación.
3. El sistema informa que no es posible asociar dicha empresa.

**Resultado:** El servicio no es creado.

---

### A3 — Datos inválidos

En el paso 7:

1. El sistema detecta errores de validación.
2. El sistema informa los errores encontrados.
3. El actor podrá corregir los datos y reenviar el formulario.

**Resultado:** El servicio no es creado.

---

## Postcondiciones

### Éxito

* El servicio queda registrado en el sistema.
* El servicio queda asociado al profesional creador.
* Se registra el evento de auditoría correspondiente.
* El servicio queda disponible para futuras configuraciones de agenda y reservas.

### Fallo

* No se registra ningún servicio.
* No se generan cambios en el sistema.

---

## Reglas de Negocio

### RN-09.01 — Perfil Profesional Obligatorio

Únicamente los usuarios con perfil profesional podrán crear servicios.

### RN-09.02 — Propiedad de Empresa

Un profesional solo podrá asociar empresas que le pertenezcan.

### RN-09.03 — Asociación Automática

Todo servicio creado quedará asociado automáticamente al perfil profesional que realiza la operación.

### RN-09.04 — Auditoría

Toda creación de servicio deberá registrarse en la bitácora de actividad del sistema.

### RN-09.05 — Modalidad de Atención

Todo servicio deberá definir una modalidad de atención válida:

* Presencial
* Remota
* Híbrida

### RN-09.06 — Duración Permitida

La duración de un servicio únicamente podrá tomar uno de los siguientes valores:

* 15 minutos
* 30 minutos
* 45 minutos
* 60 minutos
* 90 minutos
* 120 minutos

### RN-09.07 — Precio Válido

El precio del servicio deberá ser mayor o igual a cero.

### RN-09.08 — Fechas Consistentes

Cuando se especifique una fecha de finalización, ésta deberá ser igual o posterior a la fecha de inicio.

### RN-09.09 — Coordenadas Geográficas

Cuando se proporcione una ubicación geográfica, la latitud y longitud deberán encontrarse dentro de los rangos válidos definidos por el sistema.

### RN-09.10 — Restricciones de Agenda

El tiempo mínimo de reprogramación y el tiempo de separación entre turnos no podrán ser negativos.

### RN-09.11 — Límite de Reservas

Cuando se configure un máximo de reservas por cliente, dicho valor deberá ser mayor o igual a uno.

### RN-09.12 — Ubicación Requerida para Servicios Presenciales

Los servicios presenciales o híbridos deberán contar con información suficiente para determinar la ubicación donde se brindará la atención.

---

## Datos de Entrada

| Campo                   | Obligatorio | Descripción                                           |
| ----------------------- | ----------- | ----------------------------------------------------- |
| company_id              | No          | Empresa asociada al servicio                          |
| name                    | Sí          | Nombre del servicio                                   |
| description             | No          | Descripción detallada                                 |
| price                   | Sí          | Precio del servicio                                   |
| duration_minutes        | Sí          | Duración permitida (15, 30, 45, 60, 90 o 120 minutos) |
| modality                | Sí          | Modalidad de atención                                 |
| address                 | No          | Dirección física                                      |
| link                    | No          | URL para atención remota                              |
| latitude                | No          | Latitud de ubicación                                  |
| longitude               | No          | Longitud de ubicación                                 |
| max_bookings_per_client | No          | Máximo de reservas por cliente                        |
| min_reschedule_minutes  | Sí          | Tiempo mínimo para reprogramar                        |
| buffer_minutes          | Sí          | Tiempo de separación entre turnos                     |
| starts_at               | No          | Fecha de inicio de disponibilidad                     |
| ends_at                 | No          | Fecha de finalización de disponibilidad               |
| is_active               | No          | Estado inicial del servicio                           |

---

## Datos de Salida

### Creación Exitosa

* Confirmación de creación.
* Información completa del servicio creado.

### Error

* Mensaje descriptivo indicando la causa del rechazo.

---

## Realización Técnica

### Componentes involucrados

| Componente                   | Responsabilidad                |
| ---------------------------- | ------------------------------ |
| `ServiceController::store()` | Punto de entrada HTTP          |
| `StoreServiceRequest`        | Validación de datos            |
| `ServicePolicy`              | Control de autorización        |
| `StoreServiceAction`         | Implementación del caso de uso |
| `Service`                    | Persistencia del servicio      |
| `ActivityLogger`             | Registro de auditoría          |

### Secuencia de ejecución

1. El cliente realiza una solicitud de creación de servicio.
2. El sistema valida los datos mediante `StoreServiceRequest`.
3. El sistema verifica mediante `ServicePolicy` que el usuario posea rol profesional.
4. `StoreServiceAction` obtiene el perfil profesional asociado al usuario autenticado.
5. El sistema verifica que la empresa seleccionada pertenezca al profesional.
6. El sistema crea el servicio asociándolo automáticamente al perfil profesional.
7. El sistema registra el evento de auditoría `ServiceCreated`.
8. El sistema devuelve la información del servicio creado.

### Endpoint asociado

**Método:** `POST`

**Ruta:** `/services`

### Control de acceso

* Requiere autenticación JWT.
* Requiere perfil profesional.
* El profesional únicamente puede crear servicios asociados a sí mismo.

### Auditoría

La creación de un servicio genera el evento:

`ServiceCreated`

Incluyendo:

* Identificador del servicio.
* Profesional propietario.
* Empresa asociada.
* Modalidad.
* Precio.
* Duración.
* Estado de activación.

### Respuesta Exitosa

**HTTP 201 Created**

```json
{
  "message": "Servicio creado correctamente",
  "service": {
    "...": "..."
  }
}
```

### Posibles Errores

**HTTP 403 Forbidden**

* Usuario sin perfil profesional.
* Empresa no perteneciente al profesional.

**HTTP 422 Unprocessable Entity**

* Errores de validación de los datos enviados.

### Clases de Implementación

* `App\Http\Controllers\Service\ServiceController`
* `App\Actions\Service\StoreServiceAction`
* `App\Http\Requests\Service\StoreServiceRequest`
* `App\Policies\ServicePolicy`
* `App\Models\Service\Service`
* `App\Support\ActivityLog\ActivityLogger`
