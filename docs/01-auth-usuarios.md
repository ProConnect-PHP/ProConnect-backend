# 01 — Autenticación y Gestión de Usuarios

## Alcance

Este documento agrupa los casos de uso relacionados con la identidad del usuario dentro de ProConnect: registro, inicio de sesión, selección de tipo de usuario, recuperación y cambio de contraseña.

El sistema maneja dos perfiles funcionales principales:

- **Cliente**: usuario que busca, compra paquetes, reserva turnos y consume servicios.
- **Profesional**: usuario que publica servicios, configura disponibilidad, vende paquetes y atiende reservas.

> Estado general del módulo: **parcialmente implementado**. Registro/login y flujo base están contemplados. OAuth queda como pendiente o no validado.

---

## CU01 — Registro de Usuario

### Estado

- Estado funcional: 🟡 Parcial / base implementada.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Usuario no autenticado.

### Objetivo

Permitir que un usuario cree una cuenta dentro de la plataforma utilizando correo electrónico y contraseña.

### Precondiciones

- El usuario no debe estar autenticado.
- El correo ingresado no debe existir previamente en el sistema.

### Flujo principal

1. El usuario accede a la pantalla de registro.
2. El sistema muestra un formulario con nombre, apellido, correo, contraseña y confirmación de contraseña.
3. El usuario completa los datos.
4. El frontend ejecuta validaciones básicas de formato.
5. El backend valida unicidad del correo y reglas de contraseña.
6. El sistema crea el usuario.
7. El usuario queda habilitado para iniciar sesión.
8. En el primer ingreso, el sistema lo deriva a la selección de tipo de usuario.

### Flujos alternativos

#### A1 — Correo ya registrado

1. El usuario ingresa un correo existente.
2. El backend rechaza la operación.
3. El sistema informa que el correo ya se encuentra registrado.
4. Se ofrece iniciar sesión o utilizar otro correo.

#### A2 — Contraseñas no coinciden

1. El usuario ingresa contraseña y confirmación diferentes.
2. El sistema rechaza el formulario.
3. Se muestra mensaje de validación.

### Reglas de negocio

- El correo electrónico es único.
- La contraseña debe cumplir política mínima de seguridad.
- El usuario debe iniciar sin tipo funcional definido o con estado pendiente de selección.
- El registro OAuth con Google se considera pendiente si no está integrado.

### Consideraciones técnicas

- Backend Laravel API REST.
- Validación mediante Form Request o equivalente.
- Persistencia transaccional simple.
- Respuesta JSON uniforme.
- No versionar secretos ni credenciales OAuth.

### Pendientes

- ❌ OAuth Google.
- 🔒 Validar endurecimiento de password policy.
- 🔒 Validar si existe verificación de email.

---

## CU02 — Iniciar Sesión

### Estado

- Estado funcional: 🟡 Parcial / base implementada.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Usuario registrado.

### Objetivo

Permitir que un usuario autenticado acceda al sistema según su rol funcional.

### Precondiciones

- El usuario debe estar registrado.
- La cuenta debe estar activa.

### Flujo principal

1. El usuario accede al login.
2. Ingresa correo electrónico y contraseña.
3. El backend valida credenciales.
4. Si son correctas, genera token/sesión.
5. El frontend almacena el estado de autenticación.
6. El sistema redirige según el estado del usuario:
   - Si no tiene tipo de usuario, va a selección de tipo.
   - Si es cliente, va al home/dashboard cliente.
   - Si es profesional, va al dashboard profesional.

### Flujos alternativos

#### A1 — Credenciales inválidas

1. El usuario ingresa datos incorrectos.
2. El backend responde con error genérico.
3. El frontend muestra que las credenciales no son correctas.

#### A2 — Usuario sin tipo funcional

1. El usuario inicia sesión correctamente.
2. El backend indica que no tiene tipo asignado.
3. El frontend lo envía a selección de tipo de usuario.

### Reglas de negocio

- No diferenciar públicamente si falló correo o contraseña.
- Proteger endpoints mediante middleware de autenticación.
- La autorización debe depender del tipo de usuario y permisos.

### Consideraciones técnicas

- Puede utilizar Sanctum o JWT.
- El token debe enviarse en `Authorization: Bearer <token>` si se usa JWT.
- Frontend debe manejar expiración de sesión.
- Guards frontend no reemplazan autorización backend.

### Pendientes

- 🟡 Confirmar autorización granular.
- ❌ OAuth Google.
- 🔒 Validar refresh/logout/invalidation.

---

## CU03 — Selección de Tipo de Usuario

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: ✅ Documentado.
- Prioridad: Alta.

### Actor principal

Usuario autenticado sin tipo funcional asignado.

### Objetivo

Permitir que un usuario elija si actuará como Cliente o Profesional.

### Flujo principal

1. El usuario inicia sesión por primera vez.
2. El sistema detecta que no tiene tipo de usuario.
3. El frontend muestra opciones: Cliente o Profesional.
4. El usuario selecciona una opción.
5. El backend persiste el tipo.
6. El sistema redirige:
   - Cliente: home o dashboard cliente.
   - Profesional: configuración de perfil profesional.

### Reglas de negocio

- El cambio de Profesional a Cliente no debería permitirse libremente si ya existen servicios, reservas o paquetes asociados.
- El tipo de usuario debe persistirse en backend.
- Las rutas deben protegerse por tipo funcional.

### Consideraciones técnicas

- Este caso debe estar protegido por autenticación.
- La operación debe validar estado actual para evitar reasignaciones inválidas.
- Conviene modelar roles/permisos con una capa explícita y no solo con un string simple.

### Pendientes

- 🔒 Validar si el backend impide cambios inconsistentes.
- 🔒 Validar guards en frontend.

---

## CU06 — Cambiar Contraseña

### Estado

- Estado funcional: 🟡 Parcial / pendiente de validación.
- Estado documental: ✅ Documentado.
- Prioridad: Media.

### Actor principal

Usuario autenticado o usuario con token de recuperación válido.

### Objetivo

Permitir modificar la contraseña de acceso.

### Flujo principal desde perfil

1. El usuario accede a configuración de cuenta.
2. Selecciona cambiar contraseña.
3. Ingresa contraseña actual, nueva contraseña y confirmación.
4. El backend valida contraseña actual.
5. El backend valida política de nueva contraseña.
6. Se actualiza la contraseña.
7. El sistema confirma el cambio.

### Flujo desde recuperación

1. El usuario accede desde un link de recuperación.
2. El sistema valida token.
3. Ingresa nueva contraseña y confirmación.
4. El backend actualiza la contraseña.
5. El token queda invalidado.

### Reglas de negocio

- La contraseña nueva no debe persistirse en texto plano.
- El token de recuperación debe expirar.
- El cambio debe invalidar tokens temporales.

### Pendientes

- 🔒 Validar implementación desde perfil.
- 🔒 Validar implementación desde recuperación.

---

## CU07 — Recuperar Contraseña

### Estado

- Estado funcional: 🟡 Parcial.
- Estado documental: ✅ Documentado.
- Prioridad: Media.

### Actor principal

Usuario no autenticado.

### Objetivo

Permitir iniciar un flujo seguro de recuperación de contraseña.

### Flujo principal

1. El usuario accede a recuperar contraseña.
2. Ingresa correo electrónico.
3. El sistema responde de forma genérica.
4. Si existe cuenta asociada, genera token de recuperación.
5. Se envía email con link de recuperación.
6. El usuario accede al link y define nueva contraseña.

### Reglas de negocio

- No revelar si el correo existe.
- El token debe ser único, seguro y con expiración.
- El link debe apuntar correctamente al frontend.

### Pendientes

- 🟡 Corregir link roto de email si sigue existiendo.
- 🔒 Validar template de email.
- 🔒 Validar expiración de token.
