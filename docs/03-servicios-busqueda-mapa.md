# 03 — Servicios, Búsqueda, Filtros y Mapa

## Alcance

Este documento agrupa los casos de uso vinculados a la publicación, modificación, deshabilitación, búsqueda y visualización de servicios profesionales en ProConnect.

> Estado general del módulo: **mayormente implementado**, con pendientes de validación en reglas avanzadas, filtros completos y protección frente a reservas futuras.

---

## CU09 — Crear Servicio

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Profesional autenticado.

### Objetivo

Permitir que un profesional publique un servicio reservable dentro de la plataforma.

### Datos del servicio

- Nombre.
- Descripción.
- Categoría o tipo de servicio.
- Modalidad: presencial, virtual/remota o híbrida.
- Precio.
- Duración.
- Datos empresariales asociados.
- Contacto.
- Ubicación o sitio web.
- Horarios.
- Días no disponibles.
- Excepciones.
- Fecha inicio.
- Fecha fin.
- Máximo de turnos por persona.
- Tiempo mínimo para reprogramar.
- Buffer entre turnos.

### Flujo principal

1. El profesional accede a crear servicio.
2. El sistema muestra formulario de carga.
3. El profesional completa datos básicos, precio, duración y modalidad.
4. Si la modalidad es presencial o híbrida, informa ubicación física.
5. Si la modalidad es virtual o híbrida, informa link o habilita atención integrada.
6. Configura horarios, restricciones y disponibilidad.
7. El backend valida propiedad, datos obligatorios y solapamientos.
8. El sistema crea el servicio.
9. El servicio queda visible para clientes si está activo.

### Reglas de negocio

- Solo profesionales pueden crear servicios.
- Un servicio pertenece a un profesional.
- La modalidad presencial/híbrida requiere ubicación.
- La modalidad virtual/híbrida requiere mecanismo remoto.
- La disponibilidad no debe solaparse de forma inválida con agenda existente.
- Un profesional no debe poder crear servicios sobre datos empresariales de otro profesional.

### Consideraciones técnicas

- Validar en backend aunque el frontend tenga validaciones.
- Usar transacciones si se crean servicio + disponibilidad + reglas asociadas.
- Separar `Service`, `ServiceAvailability`, `ServiceException`, `BusinessProfile`.
- Evitar mezclar lógica de agenda en controller.

### Pendientes

- 🔒 Validar algoritmo real de solapamiento.
- 🟡 Documentar payload de creación.

---

## CU10 — Modificar Servicio

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Profesional propietario del servicio.

### Objetivo

Permitir modificar datos de un servicio sin romper reservas existentes.

### Flujo principal

1. El profesional selecciona un servicio propio.
2. El sistema carga datos actuales.
3. El profesional modifica datos permitidos.
4. El backend valida propiedad.
5. El backend valida impacto sobre reservas futuras.
6. El sistema persiste cambios.
7. Los nuevos datos aplican a reservas futuras según reglas definidas.

### Reglas de negocio

- No se pueden aplicar cambios que invaliden turnos pendientes o confirmados.
- Si existen turnos pendientes, no se deben agregar excepciones que afecten esos días.
- Cambios de precio deberían aplicar solo a nuevas reservas.
- Cambios de modalidad deben validar ubicación o atención remota.

### Pendientes

- 🔒 Validar si se bloquean cambios sobre reservas futuras.
- 🟡 Definir si precio/modo cambia retroactivamente o solo a futuro.

---

## CU11 — Eliminar o Deshabilitar Servicio

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: ✅ Documentado.
- Prioridad: Media.

### Actor principal

Profesional propietario del servicio.

### Objetivo

Permitir que un profesional deje de ofrecer un servicio sin destruir histórico.

### Flujo principal

1. El profesional selecciona eliminar/deshabilitar servicio.
2. El backend verifica si existen turnos pendientes o futuros.
3. Si no existen conflictos, el servicio se marca como finalizado/inactivo.
4. El servicio deja de aparecer como reservable.
5. El histórico permanece disponible.

### Reglas de negocio

- No eliminar físicamente servicios con reservas históricas.
- Si existen turnos pendientes, bloquear eliminación destructiva.
- La fecha fin puede usarse como mecanismo de baja lógica.
- Un servicio eliminado puede rehabilitarse modificando fecha fin o estado si la regla lo permite.

### Pendientes

- 🔒 Validar si se usa `deleted_at`, `status` o `end_date`.
- 🟡 Documentar diferencia entre eliminar, desactivar y finalizar.

---

## CU12 — Seleccionar Servicio

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Cliente autenticado o visitante, según alcance.

### Objetivo

Permitir explorar servicios, aplicar filtros y acceder al detalle para reservar.

### Filtros

- Tipo/categoría de servicio.
- Modalidad.
- Precio.
- Ubicación.
- Disponibilidad.
- Calificaciones.
- Profesional.

### Flujo principal

1. El cliente accede al listado de servicios.
2. El sistema muestra servicios activos.
3. El cliente aplica filtros.
4. El backend devuelve resultados paginados o filtrados.
5. El cliente selecciona un servicio.
6. El sistema muestra detalle completo.
7. Desde el detalle puede reservar turno o gestionar reservas existentes.

### Reglas de negocio

- Solo mostrar servicios activos y vigentes.
- El profesional no puede reservar su propio servicio.
- El cliente puede reservar más de un turno si las reglas del servicio lo permiten.
- La disponibilidad visual debe recalcularse desde backend antes de reservar.

### Pendientes

- 🔒 Validar filtros implementados backend/frontend.
- 🟡 Documentar query params.

---

## CU12.1 — Visualización Geográfica en Mapa

### Estado

- Estado funcional: ✅ Implementado.
- Estado documental: 📝 Agregado.
- Prioridad: Media.

### Actor principal

Cliente.

### Objetivo

Permitir visualizar servicios presenciales o híbridos en un mapa.

### Flujo principal

1. El cliente accede a búsqueda o detalle geográfico.
2. El sistema carga servicios con ubicación pública.
3. El frontend renderiza mapa con Mapbox.
4. El cliente puede consultar puntos o servicios cercanos.
5. El sistema permite navegar al detalle del servicio.

### Reglas de negocio

- Servicios remotos no requieren punto físico.
- La ubicación privada no debe exponerse si el profesional no lo permite.
- Los puntos deben corresponder a servicios activos.

### Consideraciones técnicas

- Mapbox en frontend.
- Token público restringido por dominio si se despliega.
- Geocodificación por dirección o persistencia de coordenadas según implementación.

### Pendientes

- 🔒 Validar si se almacena lat/lng o dirección geocodificada.
- 🔒 Validar restricciones del token Mapbox.
