# 05 — Reservas, Ciclo de Vida y Concurrencia

## Alcance

Este documento agrupa los casos de uso vinculados a reservar turnos, usar paquetes en reservas, modificar turnos y controlar el ciclo de vida de una reserva.

> Estado general del módulo: **implementado con pendientes de documentación/validación**. La reserva normal, reserva usando paquete, estados y concurrencia están contemplados. Falta documentar con precisión endpoints, estados finales y tipo de lock utilizado.

---

## CU13 — Reservar Turno

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Crítica.

### Actor principal

Cliente autenticado.

### Objetivo

Permitir que un cliente reserve un turno disponible para un servicio específico.

### Precondiciones

- El cliente está autenticado.
- El servicio existe y está activo.
- El cliente no es el profesional propietario del servicio.
- Existe disponibilidad para la fecha/hora seleccionada.

### Flujo principal

1. El cliente ingresa al detalle de un servicio.
2. El sistema muestra disponibilidad.
3. El cliente selecciona un slot.
4. El frontend envía solicitud de reserva al backend.
5. El backend valida autenticación y autorización.
6. El backend valida que el cliente no sea propietario del servicio.
7. El backend revalida disponibilidad dentro de una operación atómica.
8. El backend crea la reserva.
9. Si corresponde pago normal, la reserva queda pendiente de pago o asociada a intención de pago.
10. Si corresponde uso de paquete, se delega al CU13.1.
11. El sistema dispara emails/notificaciones.

### Reglas de negocio

- No se permite reservar servicios propios.
- Se reserva un único turno por operación.
- La reserva debe respetar duración del servicio.
- La reserva debe respetar buffers, excepciones, días no disponibles y reservas existentes.
- La disponibilidad visual no es suficiente: siempre se revalida en backend.
- El cliente puede reservar más de una vez el mismo servicio si las reglas del servicio lo permiten.

### Estados posibles

- Pendiente.
- Confirmada.
- Pagada.
- En curso.
- Finalizada.
- Cancelada.
- No asistida.

### Concurrencia

El sistema debe evitar doble reserva mediante:

- Transacciones de base de datos.
- Locks por recurso crítico.
- Validaciones atómicas.
- Revalidación de disponibilidad justo antes de persistir.

### Consideraciones técnicas

- El controller no debería implementar la lógica de agenda directamente.
- Recomendado: `CreateBookingAction`, `BookingService` o `ReserveSlotUseCase`.
- El lock puede implementarse con PostgreSQL row-level lock, advisory lock o Redis lock.
- La reserva y el consumo de paquete deben estar en la misma transacción cuando aplique.

### Pendientes

- 🔒 Validar si el lock real es DB transaction, Redis lock o ambos.
- 🟡 Documentar endpoint exacto.
- 🟡 Documentar payload de reserva.

---

## CU13.1 — Reservar Turno usando Paquete

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: 📝 Agregado.
- Prioridad: Crítica.

### Actor principal

Cliente autenticado con paquete comprado.

### Objetivo

Permitir que un cliente reserve una sesión consumiendo una unidad de un paquete adquirido previamente.

### Precondiciones

- El cliente tiene un paquete comprado.
- El paquete está activo.
- El paquete tiene sesiones disponibles.
- El paquete aplica al servicio seleccionado.
- El paquete pertenece al cliente.

### Flujo principal

1. El cliente selecciona un servicio.
2. El sistema detecta paquetes activos aplicables.
3. El cliente elige reservar usando paquete.
4. El backend valida que el paquete pertenece al cliente.
5. El backend valida vigencia y sesiones restantes.
6. El backend valida que el servicio está incluido en el paquete.
7. El backend valida disponibilidad del turno.
8. Se crea la reserva.
9. Se descuenta una sesión del paquete o se registra consumo.
10. Se envían emails al cliente y al profesional.

### Reglas de negocio

- No se puede usar un paquete vencido.
- No se puede usar un paquete sin sesiones restantes.
- No se puede usar un paquete de otro cliente.
- No se puede usar un paquete para un servicio no incluido.
- La reserva y el consumo de paquete deben ser atómicos.
- Si falla la reserva, no se consume sesión.
- Si falla el email, no se revierte la reserva.

### Emails asociados

- Email al cliente confirmando reserva con paquete.
- Email al profesional informando que el cliente reservó usando paquete.

### Datos mínimos del email al cliente

- Servicio reservado.
- Nombre del paquete.
- Fecha y hora.
- Profesional.
- Sesiones restantes.
- Vencimiento del paquete, si aplica.

### Datos mínimos del email al profesional

- Cliente.
- Servicio.
- Fecha y hora.
- Paquete utilizado.

### Pendientes

- 🔒 Validar si existe historial de consumo de paquete.
- 🟡 Documentar payload y respuesta.

---

## CU14 — Modificar Turno

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Alta.

### Actor principal

Cliente autenticado con reserva existente.

### Objetivo

Permitir cancelar o reprogramar una reserva existente según las políticas del servicio.

### Flujo principal — Reprogramar

1. El cliente accede a sus reservas.
2. Selecciona modificar turno.
3. El sistema muestra slots disponibles.
4. El cliente selecciona nueva fecha/hora.
5. El backend valida tiempo mínimo de reprogramación.
6. El backend valida disponibilidad del nuevo slot.
7. El backend actualiza la reserva.
8. El sistema notifica al cliente y al profesional.

### Flujo principal — Cancelar

1. El cliente accede a la reserva.
2. Selecciona cancelar.
3. El backend valida política de cancelación.
4. El sistema cancela la reserva.
5. Si hubo pago o paquete, aplica política correspondiente.
6. El sistema notifica al profesional.

### Reglas de negocio

- Debe respetarse el tiempo mínimo de reprogramación.
- No se puede reprogramar a un slot inválido.
- Si la reserva fue pagada, debe existir política de reembolso o crédito.
- Si la reserva usó paquete, debe definirse si se devuelve la sesión.
- Una reserva finalizada no debería poder reprogramarse.

### Pendientes

- 🟡 Definir política exacta de cancelación.
- 🟡 Definir comportamiento con paquetes ante cancelación.
- 🔒 Validar reprogramación real.

---

## CU14.1 — Gestionar Estado de Reserva

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: 📝 Agregado.
- Prioridad: Alta.

### Objetivo

Controlar transiciones válidas de una reserva durante todo su ciclo de vida.

### Estados

```text
Pendiente -> Confirmada -> Pagada -> En curso -> Finalizada
                 |             |          |
                 v             v          v
              Cancelada    Cancelada   No asistida
```

### Reglas de transición

- Una reserva pendiente puede confirmarse, pagarse o expirar.
- Una reserva confirmada puede pagarse, cancelarse o reprogramarse.
- Una reserva pagada puede pasar a en curso.
- Una reserva en curso puede finalizarse o marcarse como no asistida.
- Una reserva cancelada no debe volver a estado activo salvo política explícita.
- Las transiciones inválidas deben bloquearse en backend.

### Consideraciones técnicas

- Recomendado usar Enum o Value Object para estado.
- Centralizar transiciones en dominio/servicio, no dispersarlas en controllers.
- Los jobs/scheduler pueden mover reservas por tiempo: expiradas, próximas, en curso o finalizadas.

### Pendientes

- 🔒 Validar estados reales en base de datos.
- 🔒 Validar jobs de expiración/recordatorios.
