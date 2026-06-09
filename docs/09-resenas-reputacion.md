# 09 — Reseñas, Opiniones y Reputación

## Alcance

Este documento agrupa los casos de uso relacionados con valoración de servicios, respuestas a opiniones y eliminación lógica de contenido.

> Estado general del módulo: **implementado**, con pendientes menores de validación sobre reglas exactas de edición, eliminación y unicidad de reseña.

---

## CU20 — Valorar Servicio

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Media.

### Actor principal

Cliente que consumió un servicio.

### Objetivo

Permitir que un cliente valore un servicio luego de finalizar una sesión.

### Precondiciones

- El cliente debe estar autenticado.
- Debe existir una reserva finalizada.
- La reserva debe pertenecer al cliente.

### Flujo principal

1. El cliente accede a una reserva finalizada.
2. El sistema habilita opción de valorar servicio.
3. El cliente ingresa puntaje de 1 a 5.
4. Opcionalmente escribe un comentario.
5. El backend valida que el cliente pueda valorar.
6. El sistema persiste la opinión.
7. La valoración impacta la reputación visible del servicio/profesional.

### Reglas de negocio

- Solo se puede valorar después de consumir el servicio.
- No se debe permitir valorar servicios no reservados/finalizados.
- El puntaje debe estar entre 1 y 5.
- La descripción es opcional.
- El cliente puede modificar o borrar dentro de una ventana definida.

### Pendientes

- 🔒 Validar si existe una reseña por reserva o una por servicio.
- 🔒 Validar ventana de edición de 15 minutos.

---

## CU21 — Responder Opinión

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Media.

### Actor principal

Usuario autenticado.

### Objetivo

Permitir responder a una opinión publicada sobre un servicio.

### Flujo principal

1. El usuario visualiza una opinión.
2. Selecciona responder.
3. Escribe respuesta.
4. El backend valida autenticación y relación con la opinión.
5. Se persiste la respuesta.
6. Si responde el profesional propietario, la respuesta se resalta.

### Reglas de negocio

- La respuesta del profesional propietario debe tener prioridad visual.
- No se debería responder a opiniones eliminadas o bloqueadas.
- Debe existir trazabilidad de autor y fecha.

### Pendientes

- 🔒 Validar si cualquier usuario puede responder o solo involucrados.
- 🟡 Documentar si existen respuestas anidadas.

---

## CU22 — Eliminar Opinión

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado / ampliado.
- Prioridad: Media.

### Actor principal

Autor de la opinión.

### Objetivo

Permitir eliminar el contenido textual de una opinión sin necesariamente eliminar el puntaje asociado.

### Flujo principal

1. El autor accede a su opinión.
2. Selecciona eliminar.
3. El backend valida autoría y ventana permitida.
4. El sistema elimina u oculta el contenido textual.
5. El puntaje puede mantenerse para reputación agregada.

### Reglas de negocio

- El profesional no debe poder alterar reseñas de clientes.
- El autor solo puede modificar/eliminar bajo la política definida.
- El puntaje puede conservarse aunque se elimine el comentario.
- Debe preservarse trazabilidad mínima.

### Pendientes

- 🔒 Validar si se usa soft delete, anonimización o borrado parcial.
- 🟡 Documentar política final de retención.
