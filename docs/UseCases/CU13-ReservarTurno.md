# CU13 — Reservar Turno

## Descripción

Permite a un cliente reservar un horario disponible para un servicio profesional.

El sistema valida la disponibilidad real del servicio, las restricciones configuradas por el profesional y las reglas de negocio asociadas antes de registrar la reserva.

Opcionalmente, la reserva podrá ser asociada a un paquete previamente adquirido por el cliente.

---

## Actores

* Cliente

---

## Precondiciones

* El actor debe encontrarse autenticado.
* El servicio debe existir y encontrarse disponible para reservas.
* El horario seleccionado debe pertenecer a la disponibilidad efectiva del servicio.
* El actor no puede ser propietario del servicio.
* El actor debe cumplir las restricciones configuradas para el servicio.
* En caso de utilizar un paquete, éste debe encontrarse vigente y disponible.

---

## Disparador

El actor selecciona un horario disponible dentro de un servicio y confirma la reserva.

---

## Flujo Principal

1. El actor accede al detalle de un servicio.
2. El sistema muestra los horarios disponibles.
3. El actor selecciona un horario.
4. Opcionalmente, el actor selecciona un paquete compatible para utilizar durante la reserva.
5. El actor confirma la operación.
6. El sistema valida que el servicio continúe disponible.
7. El sistema verifica que el actor no sea propietario del servicio.
8. El sistema verifica que el actor no haya alcanzado el límite máximo de reservas permitido para dicho servicio.
9. El sistema verifica que el horario seleccionado continúe libre.
10. El sistema verifica que el horario pertenezca a la disponibilidad generada por las reglas del servicio.
11. El sistema registra la reserva.
12. El sistema asigna el estado inicial **Pendiente**.
13. En caso de utilizar un paquete, el sistema reserva una sesión del paquete seleccionado.
14. El sistema registra los datos históricos necesarios para preservar las condiciones originales de la reserva.
15. El sistema genera las notificaciones correspondientes.
16. El sistema confirma la creación de la reserva.

---

## Flujos Alternativos

### A1 — Servicio no disponible

En el paso 6:

1. El sistema detecta que el servicio no puede ser reservado.
2. El sistema rechaza la operación.
3. El sistema informa la situación al actor.

**Resultado:** No se crea la reserva.

---

### A2 — Auto-reserva

En el paso 7:

1. El sistema detecta que el actor es propietario del servicio.
2. El sistema rechaza la operación.

**Resultado:** No se crea la reserva.

---

### A3 — Límite de reservas alcanzado

En el paso 8:

1. El sistema detecta que el cliente alcanzó la cantidad máxima de reservas permitidas.
2. El sistema rechaza la operación.

**Resultado:** No se crea la reserva.

---

### A4 — Horario ocupado

En el paso 9:

1. El sistema detecta que el horario ya fue reservado.
2. El sistema rechaza la operación.

**Resultado:** No se crea la reserva.

---

### A5 — Horario inválido

En el paso 10:

1. El sistema detecta que el horario seleccionado no pertenece a la disponibilidad válida del servicio.
2. El sistema rechaza la operación.

**Resultado:** No se crea la reserva.

---

### A6 — Paquete inválido

En el paso 13:

1. El sistema detecta que el paquete seleccionado no puede utilizarse para la reserva.
2. El sistema cancela la operación.

**Resultado:** No se crea la reserva.

---

### A7 — Error de concurrencia

Durante la creación:

1. Otro usuario reserva el mismo horario simultáneamente.
2. El sistema detecta el conflicto mediante mecanismos de bloqueo.
3. Solo una de las operaciones es confirmada.

**Resultado:** Se evita la doble reserva.

---

## Postcondiciones

### Éxito

* La reserva queda registrada.
* La reserva queda asociada al cliente y al profesional.
* La reserva queda en estado **Pendiente**.
* Se registra la información histórica del servicio.
* Se genera una notificación para el profesional.
* Se publica el evento de creación de reserva.
* En caso de utilizar un paquete, se reserva una sesión del mismo.

### Fallo

* No se crea la reserva.
* No se generan cambios permanentes en el sistema.

---

## Reglas de Negocio

### RN-13.01 — Horario Futuro

Solo podrán reservarse horarios posteriores al momento actual.

### RN-13.02 — Disponibilidad Real

Solo podrán reservarse horarios generados por las reglas de disponibilidad vigentes del servicio.

### RN-13.03 — Horario Único

Un horario no podrá ser reservado por más de un cliente simultáneamente.

### RN-13.04 — Auto-reserva Prohibida

Un profesional no podrá reservar servicios publicados por sí mismo.

### RN-13.05 — Límite de Reservas

El sistema respetará la cantidad máxima de reservas configurada para cada cliente dentro de un servicio.

### RN-13.06 — Estado Inicial

Toda reserva creada iniciará en estado **Pendiente**.

### RN-13.07 — Preservación Histórica

La reserva conservará una copia de la duración y precio vigentes al momento de la contratación.

### RN-13.08 — Integración con Paquetes

Cuando una reserva utilice un paquete válido, el sistema deberá reservar una sesión disponible dentro del mismo.

### RN-13.09 — Notificaciones

La creación de una reserva generará una notificación para el profesional involucrado.

### RN-13.10 — Consistencia Transaccional

La creación de reservas deberá ejecutarse de forma transaccional para evitar inconsistencias y conflictos de agenda.

---

## Datos de Entrada

| Campo             | Obligatorio | Descripción                              |
| ----------------- | ----------- | ---------------------------------------- |
| starts_at         | Sí          | Fecha y hora de inicio de la reserva     |
| client_package_id | No          | Paquete utilizado para cubrir la reserva |

---

## Datos de Salida

### Reserva Exitosa

* Confirmación de creación.
* Información completa de la reserva.
* Estado inicial de la reserva.

### Error

* Mensaje descriptivo indicando la causa del rechazo.

---

## Realización Técnica

### Componentes involucrados

| Componente                        | Responsabilidad                          |
| --------------------------------- | ---------------------------------------- |
| `BookingController::store()`      | Punto de entrada HTTP                    |
| `StoreBookingRequest`             | Validación de datos                      |
| `CreateBookingAction`             | Implementación principal del caso de uso |
| `GenerateAvailabilitySlotsAction` | Generación de disponibilidad efectiva    |
| `ReservePackageSessionAction`     | Consumo de paquetes                      |
| `Booking`                         | Persistencia de reservas                 |
| `NotificationService`             | Generación de notificaciones             |
| `BookingCreated`                  | Evento de dominio                        |

### Secuencia de ejecución

1. El cliente selecciona un horario disponible.
2. El sistema valida los datos recibidos.
3. El sistema inicia una transacción.
4. El servicio es bloqueado para evitar conflictos concurrentes.
5. Se verifican todas las reglas de reserva.
6. Se verifica la disponibilidad efectiva.
7. Se crea la reserva con estado **Pending**.
8. Se procesa el paquete asociado si corresponde.
9. Se publica el evento `BookingCreated`.
10. Se genera una notificación para el profesional.
11. Se confirma la transacción.

### Endpoint asociado

**Método:** `POST`

**Ruta:** `/services/{service}/bookings`

### Control de acceso

* Requiere autenticación JWT.
* Requiere capacidad de actuar como cliente.
* No permite reservar servicios propios.

### Eventos generados

* `BookingCreated`

### Notificaciones generadas

* `booking.created`

### Respuesta Exitosa

**HTTP 201 Created**

```json
{
  "message": "Reserva creada correctamente",
  "booking": {
    "...": "..."
  }
}
```

### Posibles Errores

**HTTP 403 Forbidden**

* Intento de reservar un servicio propio.
* Restricciones de acceso.

**HTTP 422 Unprocessable Entity**

* Fecha inválida.
* Horario inexistente.
* Horario ocupado.
* Restricciones de negocio incumplidas.

### Clases de Implementación

* `App\Http\Controllers\Booking\BookingController`
* `App\Actions\Booking\CreateBookingAction`
* `App\Http\Requests\Booking\StoreBookingRequest`
* `App\Models\Booking\Booking`
* `App\Actions\Availability\GenerateAvailabilitySlotsAction`
* `App\Actions\Package\ReservePackageSessionAction`
* `App\Services\Notification\NotificationService`
