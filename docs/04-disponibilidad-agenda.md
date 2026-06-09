# 04 — Disponibilidad, Agenda y Reglas de Turnos

## Alcance

Este documento describe la disponibilidad avanzada que necesita ProConnect para permitir reservas seguras, evitar solapamientos y respetar las restricciones de cada profesional/servicio.

> Estado general del módulo: **parcialmente implementado**. Hay lógica de disponibilidad y reserva, pero se deben validar excepciones, feriados, buffers, pausas y algoritmo final de slots.

---

## CU28 — Configurar Disponibilidad Profesional

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: 📝 Agregado.
- Prioridad: Alta.

### Actor principal

Profesional autenticado.

### Objetivo

Permitir que el profesional configure cuándo puede recibir reservas y bajo qué restricciones.

### Configuraciones contempladas

- Días laborales.
- Horarios de atención.
- Pausas.
- Días no disponibles.
- Excepciones puntuales.
- Feriados.
- Fecha de inicio.
- Fecha de fin.
- Buffers entre sesiones.
- Duración del servicio.
- Máximo de turnos por persona.
- Tiempo mínimo para reservar.
- Tiempo mínimo para reprogramar.

### Flujo principal

1. El profesional accede a configuración de disponibilidad.
2. Define horarios recurrentes.
3. Define días no disponibles o excepciones.
4. Configura restricciones de reserva y reprogramación.
5. El sistema valida solapamientos internos.
6. El backend persiste la configuración.
7. Los clientes ven slots disponibles calculados desde esas reglas.

### Reglas de negocio

- Las excepciones tienen prioridad sobre disponibilidad recurrente.
- Los buffers bloquean tiempo antes o después de una reserva.
- No se deben generar slots sobre reservas existentes.
- No se deben generar slots sobre días no disponibles.
- No se deben invalidar reservas futuras sin política clara.
- El cálculo real de disponibilidad debe realizarse en backend.

### Modelo conceptual recomendado

```text
Professional
 └── Service
      ├── AvailabilityRule
      ├── AvailabilityException
      ├── BookingPolicy
      └── Booking[]
```

### Consideraciones técnicas

- Separar configuración de disponibilidad de cálculo de slots.
- Usar un servicio de dominio tipo `AvailabilityCalculator`.
- Evitar calcular disponibilidad únicamente en frontend.
- Para reservas concurrentes, el slot visible no alcanza: hay que revalidar dentro de una transacción.

### Pendientes

- 🔒 Validar implementación de excepciones.
- 🔒 Validar manejo de feriados.
- 🔒 Validar pausas y buffers.
- 🟡 Documentar algoritmo final de generación de slots.

---

## Algoritmo esperado de disponibilidad

### Entrada

- Servicio.
- Profesional.
- Rango de fechas consultado.
- Duración del servicio.
- Buffer entre sesiones.
- Reglas recurrentes.
- Excepciones.
- Reservas existentes.

### Salida

Lista de slots disponibles.

### Proceso esperado

1. Cargar reglas recurrentes para el servicio/profesional.
2. Expandir reglas dentro del rango consultado.
3. Aplicar excepciones y días no disponibles.
4. Dividir ventanas de atención según duración del servicio.
5. Aplicar buffers.
6. Remover slots que colisionan con reservas existentes.
7. Remover slots fuera de política de anticipación mínima.
8. Devolver slots ordenados.

### Restricción crítica

La generación de slots es solo informativa. Al reservar, el backend debe volver a validar disponibilidad dentro de una operación atómica.
