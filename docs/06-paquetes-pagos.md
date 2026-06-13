# 06 — Paquetes de Sesiones y Pagos

## Alcance

Este documento agrupa los casos de uso relacionados con la creación, modificación, eliminación y compra de paquetes de sesiones, además del flujo de pagos asociado a reservas y paquetes.

> Estado general del módulo: **implementado con documentación pendiente**. Compra de paquetes, consumo en reservas, emails y pago están contemplados. Falta documentar flujo exacto de pasarela, idempotencia y restricciones post-compra.

---

## CU15 — Crear Paquete

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Alta.

### Actor principal

Profesional autenticado.

### Objetivo

Permitir que un profesional cree paquetes de múltiples sesiones asociadas a uno o varios servicios.

### Datos principales

- Nombre.
- Descripción.
- Servicios incluidos.
- Cantidad de sesiones.
- Stock disponible.
- Descuento porcentual.
- Precio total calculado.
- Fecha inicio/fin, si aplica.
- Estado activo/inactivo.

### Flujo principal

1. El profesional accede a crear paquete.
2. Selecciona servicios propios a incluir.
3. Define cantidad de sesiones.
4. Define stock disponible.
5. Define descuento.
6. El backend calcula precio final.
7. El profesional confirma.
8. El sistema crea el paquete.

### Reglas de negocio

- Solo profesionales pueden crear paquetes.
- Los servicios incluidos deben pertenecer al mismo profesional.
- El precio final debe calcularse en backend.
- El stock debe validarse y persistirse.
- Un paquete activo puede ser comprado por clientes.

### Pendientes

- 🔒 Validar si permite múltiples servicios o un solo servicio.
- 🟡 Documentar payload.

---

## CU16 — Modificar Paquete

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Media.

### Actor principal

Profesional propietario del paquete.

### Objetivo

Permitir modificar paquetes respetando compras existentes.

### Flujo principal

1. El profesional selecciona un paquete propio.
2. El sistema carga datos actuales.
3. El backend verifica si el paquete fue comprado.
4. Si no fue comprado, permite modificar todos los campos.
5. Si ya fue comprado, limita cambios permitidos.
6. El backend persiste cambios.

### Reglas de negocio

- Un paquete vendido no debe modificarse de forma que altere derechos adquiridos.
- Si fue vendido, se recomienda permitir solo stock, estado o descuento futuro.
- Las compras existentes deben mantener sesiones y condiciones originales.

### Pendientes

- 🟡 Documentar campos bloqueados post-venta.
- 🔒 Validar implementación real de restricciones.

---

## CU17 — Eliminar Paquete

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Media.

### Actor principal

Profesional propietario del paquete.

### Objetivo

Permitir deshabilitar o eliminar paquetes sin romper compras existentes.

### Flujo principal

1. El profesional selecciona un paquete.
2. El backend verifica si existen compras.
3. Si no fue vendido, permite eliminación.
4. Si fue vendido, solicita confirmación y ajusta stock a 0 o inactiva el paquete.
5. El paquete deja de estar disponible para nuevas compras.

### Reglas de negocio

- No eliminar físicamente paquetes vendidos.
- Clientes que ya compraron deben conservar sus sesiones.
- Stock 0 impide nuevas compras pero no consumo de paquetes ya adquiridos.

### Pendientes

- 🔒 Validar estrategia: soft delete, stock 0 o status inactive.

---

## CU18 — Comprar Paquete

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Alta.

### Actor principal

Cliente autenticado.

### Objetivo

Permitir que un cliente compre un paquete publicado por un profesional.

### Precondiciones

- El paquete existe.
- El paquete está activo.
- Hay stock disponible.
- El cliente no es el profesional propietario.

### Flujo principal

1. El cliente selecciona un paquete.
2. El sistema muestra detalle, servicios incluidos, sesiones y precio.
3. El cliente confirma compra.
4. El backend valida stock y propiedad.
5. Se inicia flujo de pago.
6. La pasarela confirma el pago.
7. El backend registra la compra del paquete.
8. Se descuenta stock.
9. Se crean sesiones disponibles para el cliente.
10. Se envían emails al cliente y profesional.

### Reglas de negocio

- Un profesional no puede comprar su propio paquete.
- La compra debe controlar stock transaccionalmente.
- Una compra confirmada debe quedar asociada al cliente.
- La compra debe registrar sesiones disponibles/restantes.
- El paquete comprado debe poder usarse en reservas futuras.

### Pendientes

- 🔒 Validar si el cliente puede comprar el mismo paquete más de una vez.
- 🔒 Validar integración exacta con pago.

---

## CU18.1 — Pago de Reserva o Paquete

### Estado

- Estado funcional: ✅ Implementado / contemplado.
- Estado documental: 📝 Agregado.
- Prioridad: Crítica.

### Actor principal

Cliente autenticado.

### Objetivo

Permitir confirmar económicamente una reserva o compra de paquete mediante pasarela de pago.

### Flujo principal

1. El cliente inicia una reserva o compra de paquete.
2. El backend genera una orden/intención de pago.
3. El frontend redirige o procesa el pago.
4. La pasarela devuelve resultado.
5. El backend valida confirmación.
6. El backend actualiza estado de reserva o compra.
7. Se disparan emails y jobs correspondientes.

### Reglas de negocio

- No confiar únicamente en el frontend para confirmar pagos.
- Toda confirmación debe validarse en backend.
- El procesamiento debe ser idempotente.
- No se deben duplicar paquetes, reservas ni emails ante callbacks repetidos.
- Debe guardarse referencia externa de la pasarela.

### Consideraciones técnicas

- Usar columna `external_payment_id` o equivalente.
- Definir estado: pending, approved, rejected, cancelled.
- Implementar idempotencia por referencia externa.
- El pago exitoso debe emitir evento de dominio.

### Payment Orchestrator multi-provider

El backend soporta `simulator`, `mercadopago` y `paypal` mediante la interfaz
comun `IPaymentProviderGateway`.

Flujo:

1. El cliente crea un `PaymentIntent` para `booking` o `package`.
2. El backend calcula monto y moneda desde PostgreSQL.
3. El cliente solicita checkout y recibe `checkout_url`.
4. El provider notifica el resultado por webhook.
5. El backend valida la firma y consulta el recurso al provider.
6. Una transaccion confirma el intent, crea `Payment` y actualiza booking o
   crea `ClientPackage`.
7. Eventos de dominio disparan logs MongoDB y notificaciones.

Endpoints:

```text
POST /api/v1/payment-intents
POST /api/v1/payment-intents/{paymentIntent}/checkout
GET  /api/v1/payment-intents/{paymentIntent}
GET  /api/v1/payment-intents/{paymentIntent}/status
POST /api/v1/payments/webhooks/mercadopago
POST /api/v1/payments/webhooks/paypal
```

`payment_webhook_events` persiste una clave idempotente por evento. Si el
provider no entrega un ID, la clave deriva de provider, tipo y recurso. Los
payloads persistidos se sanitizan y no contienen credenciales ni datos de
tarjeta.

Para Mercado Pago, Checkout Pro usa `MERCADOPAGO_NOTIFICATION_URL`; no deriva
la URL desde `APP_URL`. Debe ser una URL publica y las firmas se validan con el
manifest oficial `id:{data.id};request-id:{x-request-id};ts:{ts};`, HMAC
SHA-256 y tolerancia temporal configurable. Se prueban los secrets
`MERCADOPAGO_WEBHOOK_SECRET`, `MERCADOPAGO_WEBHOOK_SECRET_TEST` y
`MERCADOPAGO_WEBHOOK_SECRET_PRODUCTION`, registrando solo el nombre logico del
secret que se intenta validar. Un evento `payment` valido se confirma
consultando `GET /v1/payments/{id}` antes de modificar el dominio. Otros tipos
de recurso y pagos con ID no numerico se persisten como `ignored` antes de
validar firma y sin consultar Mercado Pago.
`MERCADOPAGO_MODE` define explicitamente `sandbox` o `production`; no se
interpreta el prefijo de la credencial. Sandbox prioriza `sandbox_init_point`
y produccion prioriza `init_point`.

### Pendientes

- 🔒 Validar si PayPal está implementado, simulado o incompleto.
- 🟡 Documentar endpoint/callback.
- 🟡 Documentar idempotencia.
