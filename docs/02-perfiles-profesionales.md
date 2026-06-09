# 02 — Perfiles Personales y Profesionales

## Alcance

Este documento agrupa los casos de uso relacionados con la configuración del perfil de usuario y del perfil profesional dentro de ProConnect.

> Estado general del módulo: **parcialmente implementado**. La estructura funcional está definida; falta validar persistencia completa de datos profesionales, certificados, imágenes y datos empresariales.

---

## CU04 — Configurar Perfil

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Usuario autenticado.

### Objetivo

Permitir que el usuario configure su información personal y, si corresponde, su información profesional.

### Flujo principal

1. El usuario accede a su perfil.
2. Selecciona editar perfil.
3. El sistema carga datos actuales.
4. El usuario modifica la información.
5. Si existen cambios sin guardar e intenta salir, el sistema advierte al usuario.
6. El usuario guarda cambios.
7. El backend valida y persiste los datos.
8. El sistema muestra confirmación.

### Reglas de negocio

- Todo cambio debe persistirse únicamente al confirmar guardado.
- El correo no debe modificarse desde este flujo general.
- El perfil profesional solo debe estar disponible para usuarios tipo Profesional.
- El sistema debe diferenciar datos privados de datos públicos.

### Pendientes

- 🔒 Validar advertencia de cambios sin guardar en frontend.
- 🔒 Validar persistencia de imágenes.
- 🟡 Completar documentación de payloads.

---

## CU05 — Modificar Datos Personales

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: ✅ Documentado.
- Prioridad: Media.

### Actor principal

Usuario autenticado.

### Objetivo

Permitir modificar datos personales visibles o administrativos del usuario.

### Datos gestionados

- Foto/avatar.
- Nombre.
- Apellido.
- Descripción.
- Teléfono, si aplica.
- Datos básicos de contacto.

### Flujo principal

1. El usuario abre edición de datos personales.
2. El sistema muestra el formulario con datos precargados.
3. El usuario modifica campos permitidos.
4. El sistema valida datos localmente.
5. El usuario guarda.
6. El backend valida y persiste.
7. El frontend actualiza el estado del perfil.

### Reglas de negocio

- El correo no se modifica desde este caso de uso.
- Cambiar contraseña abre un caso de uso específico.
- El backend debe validar longitud y formato de campos.

### Pendientes

- 🔒 Validar carga de avatar.
- 🔒 Validar almacenamiento seguro de archivos.

---

## CU08 — Modificar Datos Profesionales

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Profesional autenticado.

### Objetivo

Permitir que un profesional configure su información pública, comercial y profesional.

### Datos gestionados

- Teléfono profesional.
- Descripción profesional.
- Títulos.
- Certificaciones.
- Datos empresariales.
- Nombre comercial.
- Razón social.
- RUT o identificador fiscal.
- Formas de contacto.
- Sitio web/redes.
- Ubicación de atención.

### Flujo principal

1. El profesional accede a edición de datos profesionales.
2. El sistema muestra la información actual.
3. El profesional modifica datos.
4. Puede agregar títulos o certificaciones.
5. Puede agregar datos empresariales.
6. Puede definir si ciertos datos son privados o reutilizables en servicios.
7. Guarda cambios.
8. El backend valida propiedad y persistencia.

### Reglas de negocio

- Solo usuarios Profesionales pueden ejecutar este caso.
- Títulos y certificaciones deben guardar nombre, fecha y comprobante si aplica.
- Los datos empresariales pueden usarse luego al crear servicios.
- La información pública debe separarse de información privada.

### Consideraciones técnicas

- Conviene separar `ProfessionalProfile`, `Certification`, `BusinessProfile` y `User`.
- Evitar guardar estructuras grandes como JSON si luego se necesita filtrar o auditar.
- Archivos o comprobantes deben pasar por storage controlado.

### Pendientes

- 🔒 Validar modelo real de certificaciones.
- 🔒 Validar si comprobantes son archivos, links o identificadores.
- 🟡 Documentar entidades de perfil profesional.
