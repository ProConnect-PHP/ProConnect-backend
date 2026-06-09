# Politicas de reservas y recordatorios

## Endpoints

Todos los endpoints requieren `auth:user_jwt`.

- `GET /api/v1/professional/me/booking-policy`
- `PUT /api/v1/professional/me/booking-policy`
- `GET /api/v1/professional/me/reminder-rules`
- `POST /api/v1/professional/me/reminder-rules`
- `PUT /api/v1/professional/me/reminder-rules/{id}`
- `DELETE /api/v1/professional/me/reminder-rules/{id}`
- `GET /api/v1/bookings/{booking}/available-actions`

Las respuestas de politicas, reglas y acciones usan la clave `data`. Los nombres
JSON son `snake_case`.

## Contrato frontend

```ts
export interface ProfessionalBookingPolicy {
  allowClientCancellation: boolean;
  cancellationCutoffMinutes: number;
  allowClientRescheduling: boolean;
  reschedulingCutoffMinutes: number;
  lateToleranceMinutes: number;
  remindersEnabled: boolean;
  cancellationPolicyText: string | null;
  reschedulingPolicyText: string | null;
  reminderRules: ProfessionalBookingReminderRule[];
}

export interface ProfessionalBookingReminderRule {
  id: string;
  minutesBeforeStart: number;
  sendEmail: boolean;
  sendDatabaseNotification: boolean;
  sendPush: boolean;
  sendWhatsapp: boolean;
  notifyClient: boolean;
  notifyProfessional: boolean;
  isActive: boolean;
}
```

El frontend puede usar `available-actions` para habilitar o deshabilitar
controles, pero `cancel` y `reschedule` vuelven a validar la politica dentro de
una transaccion y con bloqueo de la reserva.

## Procesamiento de recordatorios

El scheduler ejecuta cada minuto:

```bash
php artisan bookings:dispatch-reminders
```

El comando evalua las reglas actuales, crea un delivery idempotente y envia
`SendBookingReminderJob` a la cola `notifications`. El job revalida la reserva,
la politica y la regla antes de notificar. Email y notificacion de plataforma
estan implementados; push y WhatsApp quedan persistidos para integraciones
futuras.
