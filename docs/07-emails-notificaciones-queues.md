# 07 — Emails, Notificaciones y Colas

## Alcance

Este documento agrupa los casos de uso relacionados con emails transaccionales, notificaciones del sistema y procesamiento asíncrono mediante queues/jobs.

> Estado general del módulo: **emails y queues implementados; realtime pendiente**. Se trabajó especialmente en emails de compra de paquete, reserva usando paquete y notificación al profesional.

---

## CU23 — Enviar Email de Reserva Creada

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: 📝 Agregado.
- Prioridad: Alta.

### Actor disparador

Sistema, luego de crear una reserva.

### Objetivo

Informar al cliente y al profesional que se generó una reserva.

### Flujo principal

1. Se crea una reserva correctamente.
2. El backend emite evento de reserva creada.
3. Un listener procesa el evento.
4. Se despachan jobs de email.
5. El cliente recibe confirmación.
6. El profesional recibe aviso de nueva reserva.

### Contenido mínimo para cliente

- Servicio reservado.
- Profesional.
- Fecha y hora.
- Modalidad.
- Ubicación o link, si corresponde.
- Estado de la reserva.

### Contenido mínimo para profesional

- Cliente.
- Servicio.
- Fecha y hora.
- Modalidad.
- Estado de la reserva.

### Reglas técnicas

- El envío de email debe ejecutarse mediante job/queue.
- El fallo de email no debe revertir la reserva.
- Deben registrarse errores en logs/Horizon.
- El job debe ser reintentable.

### Pendientes

- 🔒 Validar templates finales.
- 🔒 Validar que no haya duplicados por reintentos.

---

## CU24 — Enviar Email de Compra de Paquete

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: 📝 Agregado.
- Prioridad: Alta.

### Actor disparador

Sistema, luego de confirmar pago y compra de paquete.

### Objetivo

Confirmar al cliente la compra del paquete e informar al profesional que un cliente compró su paquete.

### Flujo principal

1. El cliente compra un paquete.
2. El pago se confirma.
3. El backend registra la compra.
4. Se emite evento de paquete comprado.
5. Se despachan emails al cliente y profesional.

### Email al cliente

Debe incluir:

- Confirmación de pago.
- Nombre del paquete.
- Servicios incluidos.
- Cantidad de sesiones.
- Sesiones disponibles.
- Fecha de compra.
- Precio pagado.

### Email al profesional

Debe incluir:

- Cliente que compró.
- Paquete vendido.
- Fecha de compra.
- Importe.
- Stock restante, si aplica.

### Reglas técnicas

- Solo se envía luego de pago confirmado.
- No debe enviarse si el pago falla.
- El job debe ser idempotente o evitar duplicados.
- Los errores deben quedar visibles en Horizon/logs.

### Pendientes

- 🔒 Validar plantillas Blade/Mailable finales.
- 🔒 Validar tags de Horizon por booking/package/user.

---

## CU25 — Enviar Email de Reserva usando Paquete

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: 📝 Agregado.
- Prioridad: Alta.

### Actor disparador

Sistema, luego de crear una reserva consumiendo paquete.

### Objetivo

Informar explícitamente que la reserva fue realizada usando un paquete y mostrar datos relevantes del paquete.

### Flujo principal

1. El cliente reserva usando paquete.
2. El backend valida paquete y disponibilidad.
3. Se crea la reserva y se consume sesión.
4. Se emite evento de reserva con paquete.
5. Se despachan emails al cliente y profesional.

### Email al cliente

Debe incluir:

- Confirmación de reserva.
- Indicación clara de que usó un paquete.
- Nombre del paquete.
- Servicio reservado.
- Fecha y hora.
- Profesional.
- Sesiones restantes.
- Vencimiento del paquete, si corresponde.

### Email al profesional

Debe incluir:

- Cliente.
- Servicio reservado.
- Paquete utilizado.
- Fecha y hora.
- Datos relevantes de asistencia.

### Reglas de negocio

- El email se envía después de confirmar reserva y consumo de sesión.
- El consumo de paquete debe ser transaccional.
- Si el email falla, no se revierte la reserva.
- El error se reintenta mediante cola.

### Pendientes

- 🔒 Validar historial de consumo.
- 🔒 Validar contenido final del template.

---

## CU26 — Notificaciones en Tiempo Real

### Estado

- Estado funcional: ❌ Pendiente.
- Estado documental: 📝 Agregado.
- Prioridad: Alta si el RNF se mantiene como obligatorio.

### Objetivo

Permitir notificaciones instantáneas dentro de la aplicación.

### Eventos candidatos

- Nueva reserva.
- Reserva cancelada.
- Reserva reprogramada.
- Compra de paquete.
- Pago confirmado.
- Sesión próxima.
- Inicio de videollamada.
- Cambio de disponibilidad.

### Implementación esperada

- Laravel Broadcasting.
- Laravel Echo.
- WebSockets.
- Redis.
- Canales privados por usuario/profesional.
- Eventos broadcast desde backend.

### Reglas de seguridad

- Un usuario solo puede escuchar sus propios eventos.
- Un profesional solo puede escuchar eventos de sus servicios/reservas.
- Los canales deben ser privados y autorizados.
- Las notificaciones realtime no reemplazan emails críticos.

### Pendientes

- ❌ Implementar broadcasting backend.
- ❌ Configurar Echo frontend.
- ❌ Definir canales privados.
- ❌ Definir payloads.
- ❌ Integrar UI de notificaciones.

---

## CU30 — Procesamiento Asíncrono con Queues

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: 📝 Agregado.
- Prioridad: Alta.

### Objetivo

Evitar que operaciones lentas bloqueen respuestas HTTP.

### Casos procesados por cola

- Emails de reservas.
- Emails de compra de paquetes.
- Emails de reserva usando paquete.
- Recordatorios automáticos.
- Procesamientos posteriores a pagos.
- Jobs programados.

### Reglas técnicas

- Los jobs deben ser reintentables.
- Los jobs deben loguear errores.
- Los jobs críticos deben ser idempotentes.
- Horizon puede utilizarse para monitoreo.
- Redis funciona como backend de queue.

### Pendientes

- 🟡 Documentar colas concretas.
- 🟡 Documentar workers en Docker.
- 🔒 Validar política de reintentos.
