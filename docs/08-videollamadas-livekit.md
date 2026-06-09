# 08 — Atención Remota mediante LiveKit

## Alcance

Este documento describe el caso de uso de videollamadas integradas para sesiones remotas o híbridas dentro de ProConnect.

> Estado general del módulo: **pendiente**. LiveKit está definido como arquitectura objetivo, pero falta implementación real backend/frontend.

---

## CU19 — Sesión Virtual

### Estado

- Estado funcional: ❌ Pendiente.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Alta si se mantiene como RNF obligatorio.

### Actores

- Cliente.
- Profesional.
- Sistema.

### Objetivo

Permitir que cliente y profesional realicen una sesión virtual integrada en la plataforma.

### Precondiciones

- Existe una reserva remota o híbrida.
- La reserva está confirmada/pagada según política.
- Cliente y profesional pertenecen a la reserva.
- La sesión se encuentra dentro de la ventana horaria permitida.

### Flujo principal esperado

1. La sesión se aproxima a su hora de inicio.
2. El sistema notifica al profesional.
3. El profesional inicia o habilita la sala.
4. El backend crea o resuelve una room LiveKit asociada a la reserva.
5. El backend genera token LiveKit para el profesional.
6. Cuando corresponde, el cliente recibe notificación de sala disponible.
7. El backend genera token LiveKit para el cliente.
8. Ambos ingresan a la sala.
9. La sala permite audio, video y chat.
10. El profesional puede finalizar la sesión.
11. El sistema registra inicio y fin.

### Reglas de negocio

- Solo participantes de la reserva pueden entrar.
- El profesional puede tener permisos superiores.
- El cliente no debe entrar antes de la ventana permitida.
- La sala debe asociarse a una reserva concreta.
- La sesión no debería quedar pública ni reusable por terceros.
- La API secret de LiveKit nunca debe estar en frontend.

### Arquitectura esperada

```text
Frontend SPA
   |
   | solicita token de sala
   v
Laravel API
   |
   | valida reserva, usuario, horario y permisos
   v
LiveKit Server / Cloud
   |
   | room + access token
   v
Cliente / Profesional conectan por WebRTC
```

### Endpoints esperados

```http
POST /api/bookings/{bookingId}/livekit/token
POST /api/bookings/{bookingId}/livekit/start
POST /api/bookings/{bookingId}/livekit/end
```

### Payload conceptual de token

```json
{
  "room": "booking_019e...",
  "token": "jwt-livekit",
  "url": "wss://livekit.example.com",
  "role": "professional"
}
```

### Consideraciones técnicas

- Usar SDK server-side de LiveKit en Laravel o generación JWT compatible.
- El identity del token debe mapear a user id.
- La room puede nombrarse con id de reserva.
- Los permisos deben diferenciar cliente/profesional.
- Token con expiración corta.
- La UI debe manejar permisos de cámara/micrófono.

### Pendientes

- ❌ Configurar LiveKit server/cloud.
- ❌ Crear servicio backend `LiveKitTokenService`.
- ❌ Crear endpoints de token/start/end.
- ❌ Crear componente frontend de sala.
- ❌ Integrar con reservas remotas/híbridas.
- ❌ Integrar notificaciones realtime.
- ❌ Registrar inicio/fin de sesión.
