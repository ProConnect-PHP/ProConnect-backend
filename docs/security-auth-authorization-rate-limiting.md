# Seguridad: autenticacion, autorizacion y rate limiting

## Objetivo

ProConnect cumple el requisito academico:

> Autenticacion y autorizacion: sistema de login con control de roles.

La API separa tres controles:

1. **Autenticacion**: comprueba la identidad mediante JWT.
2. **Autorizacion por rol**: comprueba si el usuario es `client` o `professional`.
3. **Autorizacion por ownership**: comprueba si el recurso pertenece al cliente o profesional autenticado.

Superar un control no implica superar los demas. Por ejemplo, un profesional
autenticado no puede editar un servicio de otro profesional.

## Autenticacion

- Paquete: `tymon/jwt-auth`.
- Guard API: `user_jwt`.
- Middleware: `auth:user_jwt`.
- Header esperado: `Authorization: Bearer <token>`.
- Login: `POST /api/v1/auth/login`.
- Registro: `POST /api/v1/auth/register`.
- Refresh: `POST /api/v1/auth/refresh`.
- Logout: `POST /api/v1/auth/logout`.

El frontend Angular puede ocultar o redirigir pantallas, pero la decision final
siempre se toma en el backend.

## Modelo de roles

`App\Enums\UserRole` define:

- `client`: consume servicios, reservas, paquetes, pagos y resenas.
- `professional`: publica y gestiona sus servicios, disponibilidad, reservas
  recibidas, paquetes, pagos, videollamadas y respuestas a resenas.

`guest` no se persiste: representa la ausencia de un usuario autenticado.

El modelo `User` castea `role` a `UserRole` y expone:

- `isClient()`
- `isProfessional()`
- `hasRole()`
- `hasAnyRole()`

El registro crea clientes por defecto. La seleccion de perfil profesional es
un cambio autenticado de `client` a `professional`; el backend impide volver
libremente de profesional a cliente.

| Area | Guest | Client | Professional |
| --- | --- | --- | --- |
| Catalogo publico | Si | Si | Si |
| Crear reserva | No | Si | No |
| Gestionar servicios | No | No | Solo propios |
| Gestionar disponibilidad | No | No | Solo propia |
| Ver reservas | No | Solo propias | Solo las asociadas a sus servicios |
| Comprar y usar paquetes | No | Solo propios | No |
| Crear resena | No | Solo reserva propia finalizada | No |
| Responder resena | No | No | Solo las de sus servicios |
| Entrar a videollamada | No | Solo reservas propias | Solo reservas propias |

## Middleware de roles

El alias `role` apunta a `EnsureUserHasRole`.

Ejemplos:

```php
Route::middleware(['auth:user_jwt', 'role:client'])->group(...);
Route::middleware(['auth:user_jwt', 'role:professional'])->group(...);
Route::middleware(['auth:user_jwt', 'role:client,professional'])->group(...);
```

Un usuario no autenticado recibe 401. Un usuario autenticado con un rol no
permitido recibe 403. Las respuestas usan el envelope JSON centralizado.

## Ownership y policies

- `BookingPolicy`: cliente propietario o profesional asociado; confirmacion
  solo para el profesional; pago solo para el cliente; LiveKit exige
  participante, estado permitido y modalidad remota o hibrida.
- `ServicePolicy`: creacion profesional y update/delete solo del owner.
- Disponibilidad: los controllers autorizan el `Service` padre antes de leer o
  modificar reglas y excepciones.
- `PackageProductPolicy`: creacion/gestion profesional y compra cliente.
- `ClientPackagePolicy`: lectura o uso segun cliente propietario y profesional
  vendedor.
- `ReviewPolicy`: creacion por el cliente de la reserva y update/delete por el
  autor. Las reglas de reserva finalizada y resena unica se validan tambien
  dentro de la accion transaccional.
- `ReviewReplyPolicy`: solo el profesional propietario.
- `PaymentIntentPolicy` y `PaymentPolicy`: cliente o profesional vinculados.
- `VideoSessionPolicy`: solo participantes de la sesion.

Los casos de uso mantienen validaciones transaccionales como segunda defensa.
El backend nunca confia solamente en un ID enviado por request.

## Proteccion LiveKit

`POST /api/v1/video-sessions/bookings/{booking}/join` usa:

```text
auth:user_jwt
role:client,professional
throttle:video-join
BookingPolicy::joinVideoSession
GenerateVideoSessionTokenUseCase
```

El token solo se genera si el usuario es cliente o profesional de la reserva,
el estado es `confirmed`, `paid` o `in_progress`, y la modalidad es `remota` o
`hibrida`.

## Rate limiting

Los limiters se registran en `AppServiceProvider` y sus valores viven en
`config/security.php`. En ejecucion normal el cache store es Redis.

| Limiter | Guest | Client | Professional |
| --- | ---: | ---: | ---: |
| api-public | 60/min | 180/min | 240/min |
| api-authenticated | 30/min | 180/min | 300/min |
| booking-write | 5/min | 20/min | 30/min |
| video-join | 3/min | 30/min | 30/min |
| payment-actions | 3/min | 10/min | 10/min |
| reviews-write | 3/min | 10/min | 10/min |

Limiters de autenticacion:

| Endpoint | Limite | Clave |
| --- | ---: | --- |
| Login | 5/min | email + IP |
| Registro | 5/min | IP |
| Refresh | 10/min | IP |

Las rutas publicas usan IP para guests y user ID para usuarios autenticados.
Los endpoints privados y sensibles usan user ID.

## Respuestas de seguridad

El handler central devuelve siempre JSON:

- 401: `Unauthorized`
- 403: `Forbidden`
- 422: `ValidationError`
- 429: `TooManyRequests`

Ejemplo:

```json
{
  "success": false,
  "error": {
    "type": "Forbidden",
    "message": "No tienes permisos para realizar esta accion.",
    "details": null
  }
}
```

No se exponen trazas ni mensajes internos.

## Ejemplos de rutas

Publicas:

- `GET /api/v1/public/services`
- `GET /api/v1/public/services/{service}`
- `GET /api/v1/public/professionals/{professionalProfile}`
- `GET /api/v1/services/{service}/availability`
- `GET /api/v1/services/{service}/reviews`

Cliente:

- `POST /api/v1/services/{service}/bookings`
- `GET /api/v1/bookings/my`
- `POST /api/v1/bookings/{booking}/payment-intents`
- `POST /api/v1/package-products/{packageProduct}/purchase`
- `POST /api/v1/bookings/{booking}/review`

Profesional:

- `POST /api/v1/services`
- `PUT /api/v1/services/{service}`
- `POST /api/v1/services/{service}/availability-rules`
- `GET /api/v1/professional/bookings`
- `POST /api/v1/bookings/{booking}/confirm`

Compartidas con ownership:

- `GET /api/v1/bookings/{booking}`
- `POST /api/v1/bookings/{booking}/cancel`
- `POST /api/v1/bookings/{booking}/reschedule`
- `POST /api/v1/video-sessions/bookings/{booking}/join`

## Validacion automatizada

Pruebas principales:

- `tests/Feature/Auth/AuthorizationTest.php`
- `tests/Feature/Security/RateLimitingTest.php`
- `tests/Feature/Booking/BookingAuthorizationTest.php`
- `tests/Feature/Video/GenerateLiveKitJoinTokenApiTest.php`
- suites existentes de services, packages, payments, reviews y video.

Las pruebas demuestran:

- 401 sin JWT.
- 403 por rol incorrecto.
- 403 por ownership ajeno.
- 429 al exceder cada limiter sensible.
- mayor capacidad para client y professional en endpoints publicos.
- token LiveKit solo para participantes y reservas elegibles.

Comandos de verificacion:

```bash
docker compose exec proconnect_laravel php artisan route:list -v
docker compose exec proconnect_laravel php artisan test
```
