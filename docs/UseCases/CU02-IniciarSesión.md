# CU02 — Iniciar Sesión

## Descripción

Permite a un usuario autenticarse dentro de la plataforma mediante correo electrónico y contraseña. Si las credenciales son válidas y la cuenta se encuentra activa, el sistema genera los tokens de autenticación necesarios para acceder a las funcionalidades protegidas según el rol del usuario.

---

## Actores

* Cliente
* Profesional
* Administrador

---

## Precondiciones

* El usuario debe encontrarse registrado en el sistema.
* El usuario debe haber confirmado su correo electrónico (si aplica).
* La cuenta debe encontrarse activa.
* El usuario no debe poseer una sesión autenticada vigente.

---

## Disparador

El actor selecciona la opción **"Iniciar Sesión"** desde la pantalla de acceso.

---

## Flujo Principal

1. El sistema muestra el formulario de inicio de sesión.
2. El actor ingresa su correo electrónico y contraseña.
3. El actor confirma el envío del formulario.
4. El sistema valida que los datos requeridos hayan sido proporcionados correctamente.
5. El sistema verifica que las credenciales correspondan a un usuario registrado.
6. El sistema verifica que la cuenta asociada se encuentre activa.
7. El sistema genera un token de acceso JWT.
8. El sistema genera un token de refresco con vigencia limitada.
9. El sistema registra el inicio de sesión exitoso en el sistema de auditoría.
10. El sistema devuelve los datos de autenticación junto con la información básica del usuario.
11. El actor accede al sistema.

---

## Flujos Alternativos

### A1 — Datos inválidos

En el paso 4:

1. El sistema detecta errores de validación.
2. El sistema informa los errores encontrados.
3. El caso de uso finaliza sin autenticar al usuario.

#### Ejemplos

* Correo no ingresado.
* Contraseña no ingresada.
* Formato de correo inválido.

---

### A2 — Credenciales incorrectas

En el paso 5:

1. El sistema detecta que las credenciales no son válidas.
2. El sistema registra el intento fallido en la bitácora de actividad.
3. El sistema informa que las credenciales ingresadas son incorrectas.
4. El caso de uso finaliza.

---

### A3 — Cuenta deshabilitada

En el paso 6:

1. El sistema detecta que la cuenta existe pero no se encuentra activa.
2. El sistema registra el intento fallido en la bitácora de actividad.
3. El sistema informa que no fue posible iniciar sesión.
4. El caso de uso finaliza.

---

### A4 — Recuperar contraseña

En el paso 1:

1. El actor selecciona la opción **"¿Olvidaste tu contraseña?"**.
2. El sistema inicia el caso de uso **CU07 — Recuperar Contraseña**.

---

## Postcondiciones

### Éxito

* El usuario queda autenticado.
* Se genera un token JWT de acceso.
* Se genera un token de refresco.
* Se registra el evento de autenticación exitosa.

### Fallo

* El usuario no es autenticado.
* No se generan tokens de acceso.
* Se registra el intento fallido cuando corresponda.

---

## Reglas de Negocio

### RN-02.01 — Validación de Credenciales

El sistema únicamente permitirá el acceso cuando el correo electrónico y la contraseña coincidan con una cuenta registrada.

### RN-02.02 — Cuenta Activa

Solo podrán autenticarse usuarios cuya cuenta se encuentre activa.

### RN-02.03 — Generación de Token de Acceso

Luego de una autenticación exitosa, el sistema emitirá un token JWT para autorizar futuras solicitudes.

### RN-02.04 — Generación de Token de Refresco

Luego de una autenticación exitosa, el sistema emitirá un token de refresco con una vigencia de siete días.

### RN-02.05 — Registro de Auditoría

Todo intento de autenticación, exitoso o fallido, deberá quedar registrado en el sistema de auditoría.

### RN-02.06 — Protección de Información

Ante errores de autenticación, el sistema no revelará información que permita determinar si el correo electrónico ingresado corresponde a una cuenta existente.

---

## Datos de Entrada

| Campo      | Tipo               | Obligatorio |
| ---------- | ------------------ | ----------- |
| Email      | Correo electrónico | Sí          |
| Contraseña | Texto              | Sí          |

---

## Datos de Salida

### Inicio de sesión exitoso

* Token de acceso.
* Token de refresco.
* Tipo de token.
* Tiempo de expiración.
* Información básica del usuario autenticado.

### Inicio de sesión fallido

* Mensaje de error indicando que la autenticación no pudo completarse.

## Realización Técnica

### Componentes involucrados

| Componente                | Responsabilidad                                           |
| ------------------------- | --------------------------------------------------------- |
| `AuthController::login()` | Punto de entrada HTTP para el inicio de sesión.           |
| `LoginRequest`            | Validación de los datos recibidos desde el cliente.       |
| `LoginAction`             | Implementación principal del caso de uso.                 |
| `JWTGuard`                | Verificación de credenciales y autenticación del usuario. |
| `AuthTokenIssuer`         | Generación de tokens de acceso y refresco.                |
| `ActivityLogger`          | Registro de eventos de auditoría.                         |

### Secuencia de ejecución

1. El cliente realiza una solicitud al endpoint de autenticación enviando correo electrónico y contraseña.
2. El controlador recibe la solicitud y delega la validación a `LoginRequest`.
3. Una vez validados los datos, el controlador ejecuta `LoginAction`.
4. `LoginAction` utiliza `JWTGuard` para verificar las credenciales ingresadas.
5. Si las credenciales son inválidas, se registra un evento de auditoría y se retorna un error de autenticación.
6. Si las credenciales son válidas, se verifica que la cuenta se encuentre activa.
7. Si la cuenta está deshabilitada, se registra el intento fallido y se rechaza la autenticación.
8. Si la cuenta es válida y activa, `AuthTokenIssuer` genera:

   * Token de acceso JWT.
   * Token de refresco.
   * Información de expiración.
9. Se registra el inicio de sesión exitoso mediante `ActivityLogger`.
10. El controlador devuelve la respuesta HTTP con los datos de autenticación.

### Endpoint asociado

**Método:** `POST`

**Ruta:** `/auth/login`

### Validaciones aplicadas

| Campo    | Validación                                    |
| -------- | --------------------------------------------- |
| email    | Obligatorio, texto y formato de correo válido |
| password | Obligatorio y tipo texto                      |

### Respuesta Exitosa

Código HTTP: **200 OK**

```json
{
  "access_token": "jwt-token",
  "refresh_token": "refresh-token",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    ...
  }
}
```

### Respuesta de Error

Código HTTP: **401 Unauthorized**

```json
{
  "error": "Unauthorized",
  "message": "Credenciales incorrectas"
}
```
