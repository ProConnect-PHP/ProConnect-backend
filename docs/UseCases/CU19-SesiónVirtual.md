# CU19 — Participar en Sesión Virtual

## Descripción

Permite a los participantes de una reserva remota acceder a una videollamada integrada dentro de la plataforma.

El sistema valida que la reserva cumpla las condiciones necesarias para habilitar la sesión virtual, genera las credenciales de acceso correspondientes y registra la participación de los usuarios involucrados.

---

## Actores

* Cliente
* Profesional

---

## Precondiciones

* El actor debe encontrarse autenticado.
* Debe existir una reserva asociada.
* La reserva debe permitir videollamada.
* La reserva debe encontrarse pagada o cubierta mediante un paquete válido.
* El actor debe ser participante legítimo de la reserva.
* La videollamada debe encontrarse dentro de la ventana de acceso permitida.

---

## Disparador

El actor selecciona la opción **"Unirse a videollamada"** desde una reserva.

---

## Flujo Principal

### Creación de Sesión Virtual

1. El actor accede a una reserva remota.
2. El sistema verifica si existe una sesión virtual asociada.
3. Si la sesión no existe, el sistema valida que la reserva permita videollamada.
4. El sistema verifica que la reserva se encuentre habilitada para acceso remoto.
5. El sistema genera una sesión virtual asociada a la reserva.
6. El sistema registra la sesión con estado **Programada**.
7. El sistema registra el evento de creación de sesión virtual.

---

### Acceso a la Sesión

8. El actor solicita ingresar a la videollamada.
9. El sistema verifica que el actor sea el cliente o el profesional asociado a la reserva.
10. El sistema verifica que la sesión no se encuentre cancelada.
11. El sistema verifica que la sesión no haya finalizado.
12. El sistema verifica que la reserva continúe habilitada para videollamada.
13. El sistema verifica que la ventana de acceso se encuentre abierta.
14. El sistema registra al participante dentro de la sesión.
15. El sistema genera las credenciales de acceso correspondientes.
16. El sistema registra la participación del usuario.
17. El sistema actualiza el estado de la sesión a **En Curso** cuando corresponde.
18. El sistema concede acceso a la videollamada.

---

## Flujos Alternativos

### A1 — Modalidad no compatible

En el paso 3:

1. El sistema detecta que la modalidad del servicio no admite videollamada.
2. El sistema rechaza la operación.

**Resultado:** No se crea la sesión virtual.

---

### A2 — Reserva sin pago

En el paso 4:

1. El sistema detecta que la reserva no se encuentra pagada ni cubierta por un paquete válido.
2. El sistema rechaza la operación.

**Resultado:** No se habilita la videollamada.

---

### A3 — Reserva finalizada

En el paso 10:

1. El sistema detecta que la reserva fue completada, cancelada o marcada como no asistida.
2. El sistema rechaza la operación.

**Resultado:** No se crea ni se habilita la sesión.

---

### A4 — Usuario no autorizado

En el paso 9:

1. El sistema detecta que el usuario no pertenece a la reserva.
2. El sistema rechaza el acceso.

**Resultado:** El usuario no puede ingresar.

---

### A5 — Sesión cancelada

En el paso 10:

1. El sistema detecta que la sesión virtual fue cancelada.
2. El sistema rechaza el acceso.

**Resultado:** El usuario no puede ingresar.

---

### A6 — Sesión finalizada

En el paso 11:

1. El sistema detecta que la sesión ya terminó.
2. El sistema rechaza el acceso.

**Resultado:** El usuario no puede ingresar.

---

### A7 — Ventana de acceso cerrada

En el paso 13:

1. El sistema detecta que la videollamada aún no se encuentra disponible o ya expiró.
2. El sistema rechaza el acceso.

**Resultado:** El usuario no puede ingresar.

---

## Postcondiciones

### Éxito

* La sesión virtual queda creada cuando corresponde.
* El participante queda registrado en la sesión.
* Se generan credenciales de acceso.
* Se registra la actividad de acceso.
* La sesión puede pasar al estado **En Curso**.
* Se registra el evento correspondiente.

### Fallo

* No se concede acceso a la videollamada.
* No se generan credenciales.
* No se modifican los estados de la sesión.

---

## Reglas de Negocio

### RN-19.01 — Modalidad Compatible

Solo las reservas asociadas a modalidades remotas o híbridas podrán utilizar videollamadas.

### RN-19.02 — Pago Requerido

La reserva deberá encontrarse pagada o cubierta por un paquete válido antes de habilitar el acceso a la videollamada.

### RN-19.03 — Participantes Autorizados

Únicamente el cliente y el profesional asociados a la reserva podrán acceder a la sesión.

### RN-19.04 — Reserva Válida

No podrán acceder a videollamadas asociadas a reservas canceladas, finalizadas o marcadas como no asistidas.

### RN-19.05 — Ventana de Acceso

El acceso a la videollamada únicamente estará disponible durante la ventana temporal configurada para la sesión.

### RN-19.06 — Sesión Única por Reserva

Cada reserva podrá poseer una única sesión virtual asociada.

### RN-19.07 — Registro de Participación

Todo acceso deberá registrar información del participante, horario de ingreso y cantidad de accesos realizados.

### RN-19.08 — Inicio Automático

La primera conexión válida podrá cambiar el estado de la sesión de **Programada** a **En Curso**.

### RN-19.09 — Auditoría

La generación de credenciales y los accesos a videollamadas deberán registrarse en la bitácora de actividad.

### RN-19.10 — Trazabilidad

Toda sesión virtual deberá conservar la relación con la reserva que la originó.

---

## Datos de Entrada

| Campo               | Obligatorio | Descripción                      |
| ------------------- | ----------- | -------------------------------- |
| booking_id          | Sí          | Reserva asociada                 |
| usuario autenticado | Sí          | Participante que intenta acceder |

---

## Datos de Salida

### Acceso Exitoso

* Identificador de sesión.
* Sala virtual.
* URL de conexión.
* Token de acceso.
* Información del participante.
* Fecha de expiración del acceso.

### Error

* Mensaje descriptivo indicando la causa del rechazo.

---

## Realización Técnica

### Componentes involucrados

| Componente                           | Responsabilidad                           |
| ------------------------------------ | ----------------------------------------- |
| `BookingVideoSessionController`      | Consulta y creación de sesiones virtuales |
| `VideoSessionJoinController`         | Acceso a videollamadas                    |
| `JoinVideoSessionController`         | Generación de credenciales LiveKit        |
| `EnsureVideoSessionForBookingAction` | Creación de sesión virtual                |
| `JoinVideoSessionAction`             | Registro de participación                 |
| `GenerateVideoSessionTokenUseCase`   | Emisión de tokens de acceso               |
| `VideoSession`                       | Persistencia de sesiones                  |
| `VideoSessionParticipant`            | Persistencia de participantes             |
| `ActivityLogger`                     | Auditoría                                 |
| `LiveKitTokenService`                | Integración con LiveKit                   |

### Secuencia de ejecución

1. El usuario accede a una reserva remota.
2. El sistema verifica si existe una sesión virtual.
3. Si no existe, se ejecuta `EnsureVideoSessionForBookingAction`.
4. El sistema valida modalidad, estado y condiciones de pago.
5. Se crea una sala virtual asociada a la reserva.
6. El usuario solicita acceso.
7. El sistema verifica permisos y ventana de acceso.
8. Se registra o actualiza el participante.
9. Se generan credenciales de acceso.
10. Se registra el evento de auditoría.
11. Se devuelve la información necesaria para conectarse a la videollamada.

### Endpoint asociados

**Obtener sesión virtual**

```http
GET /bookings/{booking}/video-session
```

**Crear sesión virtual**

```http
POST /bookings/{booking}/video-session
```

**Unirse a sesión virtual**

```http
POST /video-sessions/{videoSession}/join
```

**Generar token LiveKit**

```http
POST /bookings/{booking}/join-video-session
```

### Control de acceso

* Requiere autenticación JWT.
* Solo pueden acceder participantes de la reserva.
* El acceso es validado mediante Policies y reglas de dominio.

### Eventos generados

* `VideoSessionCreated`
* `VideoSessionJoined`

### Auditoría

Se registra el evento:

* `VideoSessionTokenIssued`

Incluyendo:

* Reserva asociada.
* Sala virtual.
* Participante.
* Rol del participante.
* Fecha de expiración del acceso.

### Integraciones Externas

* LiveKit para videollamadas en tiempo real.
* Sistema interno de auditoría.
* Sistema interno de eventos de dominio.
