# PROCONNECT

Plataforma SaaS orientada a la gestión de servicios profesionales.

---

# 🚀 Stack Tecnológico

## Backend

* PHP 8.5
* Laravel 13
* PostgreSQL
* Redis
* MongoDB 7
* Mailpit
* Docker Compose

## Arquitectura

* API REST
* Laravel Actions Pattern
* Form Requests
* Json Resources
* PostgreSQL
* MongoDB complementario para actividad y auditoria
* JWT Authentication
* Arquitectura modular y escalable

---

# 🐳 Docker

## Levantar todo el entorno

```bash
docker compose up -d
```

---

# 🌐 Endpoints Locales

## API

```text
http://localhost:8000/api/v1/...
```

**Nota:** Como estamos usando Laravel Sail, este ya expone la app en el puerto 80

```text
http://localhost:80/api/v1/...
```

## Mailpit

Interfaz para testing de emails en desarrollo.

```text
http://localhost:8025
```

---

# 🧠 Comandos importantes

## Ejecutar comandos Artisan dentro del contenedor

```bash
docker compose exec proconnect_laravel php artisan ...
```

Ejemplo:

```bash
docker compose exec proconnect_laravel php artisan migrate
```

---

# ⚡ Comandos importantes de Artisan

---

## Levantar aplicación

```bash
php artisan serve
```

> Comando para levantar el servidor de Laravel manualmente. En este caso NO es necesario porque estamos usando Sail, y ya al levantar el docker expone el puerto 80 con la app levantada

---

## Migraciones

### Ejecutar migraciones

```bash
php artisan migrate
```

Migra todo lo que se encuentra en:

```text
database/migrations
```

---

### Reiniciar base de datos completamente

```bash
php artisan migrate:fresh
```

* Elimina TODAS las tablas
* Ejecuta nuevamente todas las migraciones

---

### Migrar + seeders

```bash
php artisan migrate --seed
```

* Ejecuta migraciones
* Alimenta la base de datos con seeders

---

## Seeders

### Ejecutar seeders manualmente

```bash
php artisan db:seed
```

---

# 🏗️ Generación de clases

---

## Controllers

```bash
php artisan make:controller User/UserController --api
```

Crea un controller API con:

* index
* store
* show
* update
* destroy

---

## Resources

```bash
php artisan make:resource User/UserResource
```

Crea un JsonResource para transformar respuestas JSON.

---

## Models

### Crear modelo

```bash
php artisan make:model User
```

---

### Crear modelo + migración

```bash
php artisan make:model User -m
```

---

## Migraciones

```bash
php artisan make:migration create_users_table
```

> Recomendado usar:

```bash
php artisan make:model User -m
```

---

## Rutas

```bash
php artisan route:list
```

> Sirve para ver las todas las rutas de la api y su metodo HTTP

## Actions

```bash
php artisan make:class Actions/User/StoreUserAction
```

Usaremos el patrón de diseño **Action Pattern**.

---

## Form Requests

```bash
php artisan make:request User/StoreUserRequest
```

Las Form Requests permiten:

* Validar requests
* Centralizar reglas
* Mantener controllers limpios
* Evitar lógica de validación repetida

---

# 🧠 Action Pattern

---

## ¿Qué es?

El Action Pattern busca mover la lógica de negocio fuera del controller.

En vez de tener controllers enormes con lógica mezclada:

```php
public function store(Request $request)
{
    // lógica
}
```

Creamos clases específicas que representan acciones concretas del dominio:

```php
CreateUserAction
StoreBookingAction
CancelBookingAction
PurchasePackageAction
```

---

# ✅ Beneficios

* Controllers más limpios
* Lógica reutilizable
* Mejor testing
* Separación de responsabilidades
* Código más mantenible
* Escalabilidad

---

# 📁 Estructura

```text
app/
├── Actions/
│   └── User/
│       ├── StoreUserAction.php
│       ├── UpdateUserAction.php
│       └── ShowUserAction.php
```

---

# ✅ Ejemplo de Action

```php
<?php

namespace App\Actions\User;

use App\Http\Requests\User\StoreUserRequest;
use App\Models\User\User;

class StoreUserAction
{
    public function __invoke(StoreUserRequest $request): User
    {
        return User::create($request->validated());
    }
}
```

---

# ✅ Uso desde el Controller

```php
public function store(
    StoreUserRequest $storeUserRequest,
    StoreUserAction $storeUserAction
): JsonResponse
{
    $user = $storeUserAction($storeUserRequest);

    return response()->json(
        [
            'message' => 'User created successfully',
            'user' => new UserResource($user),
        ],
        Response::HTTP_CREATED
    );
}
```

---

# 🧼 Arquitectura utilizada

```text
Controller
→ FormRequest
→ Action
→ Model
→ Resource
→ JSON Response
```

---

# 📁 Estructura actual

```text
app/
├── Actions/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Models/
└── Providers/
```

---

# 🗄️ Base de Datos

## PostgreSQL

Variables importantes del `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=proconnect_pgsql
DB_PORT=5432
DB_DATABASE=proconnect
DB_USERNAME=sail
DB_PASSWORD=password
```

---

# 📬 Mailpit

Configuración:

```env
MAIL_MAILER=smtp
MAIL_HOST=proconnect_mailpit
MAIL_PORT=1025
MAIL_SCHEME=null
```

Dashboard:

```text
http://localhost:8025
```

---

# 🔥 Redis

Configuración:

```env
REDIS_CLIENT=phpredis
REDIS_HOST=proconnect_redis
REDIS_PORT=6379
```

---

# Payment Orchestrator multi-provider

ProConnect implementa una arquitectura de pagos desacoplada con tres providers:

* `simulator` para desarrollo y tests.
* `mercadopago` mediante Checkout Pro.
* `paypal` mediante Orders API v2.

PostgreSQL almacena intenciones, pagos confirmados y eventos de webhook. MongoDB
registra actividad y auditoria sin bloquear el flujo principal cuando no esta
disponible. El dominio usa `IPaymentProviderGateway` y no depende de estados
externos ni SDKs de un proveedor.

## Configuracion

```env
PAYMENT_DEFAULT_PROVIDER=simulator
PAYMENT_ENABLED_PROVIDERS=simulator,mercadopago,paypal
PAYMENT_SIMULATOR_ENABLED=true
PAYMENTS_CURRENCY=UYU
PAYMENT_INTENT_EXPIRATION_MINUTES=30
```

Las credenciales `MERCADOPAGO_*` y `PAYPAL_*` deben configurarse como secretos
del entorno de despliegue. No se versionan tokens ni client secrets.
En produccion, `PAYMENT_SIMULATOR_ENABLED` debe permanecer en `false`.

Checkout Pro requiere una URL publica explicita. En desarrollo se debe usar un
tunel como ngrok o Cloudflare Tunnel; `localhost`, `127.0.0.1` y direcciones IP
privadas se rechazan antes de crear la preferencia:

```env
MERCADOPAGO_MODE=sandbox
MERCADOPAGO_NOTIFICATION_URL=https://tu-tunel.example/api/v1/payments/webhooks/mercadopago
MERCADOPAGO_WEBHOOK_SECRET=
MERCADOPAGO_WEBHOOK_SECRET_TEST=
MERCADOPAGO_WEBHOOK_SECRET_PRODUCTION=
MERCADOPAGO_WEBHOOK_SIGNATURE_REQUIRED=true
MERCADOPAGO_WEBHOOK_SIGNATURE_TOLERANCE_SECONDS=300
```

`MERCADOPAGO_MODE` acepta `sandbox` y `production`. El modo nunca se infiere
desde el prefijo del access token. Sandbox prioriza `sandbox_init_point` y
produccion prioriza `init_point`, con fallback al otro campo si falta.

Las URLs de retorno reciben `payment_intent_id`. La firma del webhook se valida
con `x-signature`, `x-request-id`, el `data.id` de query y HMAC SHA-256. Una
firma invalida se persiste como `invalid_signature`, responde de forma
controlada y nunca consulta ni actualiza pagos. El verificador prueba los
secrets logicos `default`, `test` y `production` sin registrar sus valores.
Los eventos Mercado Pago cuyo tipo no sea `payment`, o cuyo ID de pago no sea
numerico, se persisten como `ignored` antes de validar firma y no consultan ni
modifican pagos.

PayPal no acepta `UYU` en Orders API. `PAYPAL_CURRENCY` define la moneda de la
orden y `PAYPAL_EXCHANGE_RATES` define cuantas unidades de la moneda interna
equivalen a una unidad de esa moneda. La tasa usada queda guardada como snapshot
en metadata del intent:

```env
PAYPAL_CURRENCY=USD
PAYPAL_EXCHANGE_RATES='{"UYU":40}'
```

## Crear intent de pago generico

```http
POST /api/v1/payment-intents
Authorization: Bearer {TOKEN_CLIENTE}
Content-Type: application/json

{
  "payable_type": "booking",
  "payable_id": "uuid",
  "provider": "mercadopago"
}
```

`payable_type` admite `booking` y `package`. El backend calcula el monto desde
`booking.price_snapshot` o `package_products.price`; ignora cualquier monto
enviado por el frontend.

La ruta compatible para reservas sigue disponible:

```http
POST /api/v1/bookings/{booking}/payment-intents
Authorization: Bearer {TOKEN_CLIENTE}
```

## Crear checkout

```http
POST /api/v1/payment-intents/{paymentIntent}/checkout
Authorization: Bearer {TOKEN_CLIENTE}
Content-Type: application/json

{
  "provider": "paypal"
}
```

La respuesta incluye `payment_intent.checkout_url`. El frontend redirige a esa
URL y, al volver, consulta:

```http
GET /api/v1/payment-intents/{paymentIntent}/status
```

El redirect no confirma pagos. La confirmacion ocurre por webhook y consulta
server-to-server al provider.

## Webhooks publicos

```http
POST /api/v1/payments/webhooks/mercadopago
POST /api/v1/payments/webhooks/paypal
```

Los webhooks no usan JWT. MercadoPago valida HMAC con `x-signature`; PayPal usa
`/v1/notifications/verify-webhook-signature`. Cada evento se persiste con una
clave idempotente antes de procesarse. Eventos repetidos no duplican pagos,
bookings pagadas ni paquetes comprados.

## Simular pago exitoso

```http
POST /api/v1/payment-intents/{paymentIntent}/simulate-success
Authorization: Bearer {TOKEN_CLIENTE}
```

Resultado esperado:

```txt
payment_intent.status = succeeded
payments row creada
booking.status = paid
booking.paid_at != null
```

## Simular pago fallido

```http
POST /api/v1/payment-intents/{paymentIntent}/simulate-failure
Authorization: Bearer {TOKEN_CLIENTE}
Content-Type: application/json

{
  "failure_reason": "Tarjeta simulada rechazada."
}
```

Resultado esperado:

```txt
payment_intent.status = failed
booking.status = confirmed
payments count = 0
```

## Listar pagos

```http
GET /api/v1/payments/my
GET /api/v1/professional/payments
```

## Reglas

* Solo se pueden pagar reservas `confirmed`.
* No se puede pagar `pending`, `paid`, `in_progress`, `completed`, `cancelled` ni `no_show`.
* El monto sale del backend desde `booking.price_snapshot`.
* `payments.payment_intent_id` es unico para impedir doble procesamiento.
* Si un intent falla, se puede crear un nuevo intent.
* `payment_intents` registra intentos; `payments` registra pagos finales.
* Las transacciones y locks evitan carreras entre dobles confirmaciones de pago.

---

# Paquetes de sesiones

El sistema permite que profesionales creen paquetes de multiples sesiones. El
flujo multi-provider crea el `ClientPackage` solamente despues de confirmar el
pago. La compra simulada directa existente se conserva como compatibilidad para
demo, pero las integraciones nuevas deben usar `POST /api/v1/payment-intents`.

## Crear paquete profesional

```http
POST /api/v1/professional/package-products
Authorization: Bearer {TOKEN_PROFESIONAL}
Content-Type: application/json

{
  "service_id": "service-id",
  "name": "Pack 4 sesiones online",
  "description": "Ideal para seguimiento mensual.",
  "sessions_count": 4,
  "price": 5600,
  "currency": "UYU",
  "validity_days": 60,
  "is_active": true
}
```

## Listar paquetes publicos

```http
GET /api/v1/public/package-products
GET /api/v1/services/{service}/package-products
```

## Comprar paquete simulado

```http
POST /api/v1/package-products/{packageProduct}/purchase
Authorization: Bearer {TOKEN_CLIENTE}
```

Esto crea un `client_package` activo con snapshots de precio, moneda, cantidad de sesiones y vencimiento.

## Mis paquetes

```http
GET /api/v1/client-packages/my
GET /api/v1/client-packages/{clientPackage}
```

## Paquetes vendidos

```http
GET /api/v1/professional/client-packages
GET /api/v1/professional/client-packages/{clientPackage}
```

## Reservar usando paquete

```http
POST /api/v1/services/{service}/bookings
Authorization: Bearer {TOKEN_CLIENTE}
Content-Type: application/json

{
  "starts_at": "2026-06-10 10:00:00",
  "client_package_id": "client-package-id"
}
```

Resultado esperado:

```txt
booking creada en pending
booking.client_package_id seteado
package_session status = reserved
client_package.used_sessions incrementado
```

## Reglas

* La compra del paquete es simulada directa por ahora.
* No se crea `payment` para la compra del paquete en esta fase.
* La sesion se descuenta al reservar para evitar sobreventa.
* Si la reserva se cancela, la sesion `reserved` se libera y se decrementa `used_sessions`.
* Si la reserva se completa, `ConsumePackageSessionAction` marca la sesion como `consumed`.
* `package_sessions.booking_id` es unico para que una reserva no consuma mas de una sesion.
* `client_packages` guarda snapshots operativos y no usa soft delete; se cancela por estado.

## Emails de paquetes

La compra simulada de un paquete dispara emails transaccionales:

* `package_purchased_client`: confirmacion al cliente.
* `package_purchased_professional`: aviso al profesional.

La reserva usando paquete dispara emails especificos:

* `package_session_reserved_client`: reserva cubierta por paquete para el cliente.
* `package_session_reserved_professional`: aviso al profesional.

Estos emails usan listeners queued en la cola `emails`, se registran en `notification_logs` y no duplican el email generico de `BookingCreated` cuando la reserva tiene `client_package_id`.

Para reiniciar workers/Horizon despues de cambios de notificaciones:

```bash
docker compose exec proconnect_laravel php artisan optimize:clear
docker compose exec proconnect_laravel php artisan queue:restart
docker compose exec proconnect_laravel php artisan horizon:terminate
docker compose restart proconnect_horizon
```

---

# 🧪 Filosofía del Proyecto

* Clean code
* Controllers finos
* Lógica encapsulada
* Arquitectura mantenible
* Evitar overengineering
* Priorizar velocidad de desarrollo
* SaaS multiusuario moderno
* API desacoplada
* Preparado para escalabilidad futura

---

# 📌 Notas importantes

## UUIDs

A futuro probablemente se migrará a UUIDs para:

* evitar exponer IDs secuenciales
* mejorar seguridad
* facilitar APIs públicas

---

## Realtime

La arquitectura está pensada para integrar posteriormente:

* WebSockets
* Laravel Reverb
* LiveKit
* Notificaciones en tiempo real

---

# 📊 Datos Demo

Para desarrollo local, ProConnect incluye un dataset completo de demo que permite probar todas las características sin crear manualmente usuarios, servicios y reservas.

## Cargar datos demo

### Local (sin Docker)

```bash
php artisan migrate:fresh --seed
```

### Docker

```bash
docker compose exec proconnect_laravel php artisan migrate:fresh --seed
```

### Comando opcional (refresh solo seeders)

```bash
php artisan demo:refresh

# O con fresh migrations
php artisan demo:refresh --fresh
```

## Usuarios demo

| Rol | Email | Contraseña | Nombre |
|-----|-------|-----------|--------|
| **Cliente** | `cliente@proconnect.test` | `password123` | Cliente Demo |
| **Cliente** | `cliente2@proconnect.test` | `password123` | Cliente Secundario |
| **Cliente** | `cliente3@proconnect.test` | `password123` | Cliente Tercero |
| **Psicóloga** | `psicologa@proconnect.test` | `password123` | Dra. Valentina Acosta |
| **Coach** | `coach@proconnect.test` | `password123` | Mateo Ferreira |
| **Nutricionista** | `nutricionista@proconnect.test` | `password123` | Lucía Benítez |
| **Consultor** | `consultor@proconnect.test` | `password123` | Santiago Moreira |

## Servicios incluidos

### Psicóloga

- **Consulta psicológica inicial** (Presencial, $1,800)
  - Punta del Este - Evaluación y diagnóstico
- **Terapia online individual** (Remota, $1,600)
  - Sesión flexible por videollamada
- **Acompañamiento para ansiedad** (Híbrida, $1,900)
  - Programa especializado de 4 sesiones

### Coach Ejecutivo

- **Sesión de coaching ejecutivo** (Remota, $2,200)
  - Liderazgo y toma de decisiones
- **Mentoría de productividad** (Híbrida, $2,000)
  - Sistemas y hábitos de alto rendimiento
- **Plan de objetivos trimestral** (Remota, $3,500)
  - Planificación 90 días

### Nutricionista

- **Consulta nutricional inicial** (Presencial, $1,700)
  - Montevideo - Evaluación completa
- **Seguimiento nutricional online** (Remota, $1,200)
  - Sesión de seguimiento breve
- **Plan alimentario personalizado** (Híbrida, $2,500)
  - Plan completo con menú y compras

### Consultor de Negocios

- **Diagnóstico de negocio** (Remota, $3,000)
  - Análisis estratégico integral
- **Consultoría para emprendimientos** (Híbrida, $2,800)
  - Asesoramiento desde idea a Go-to-Market
- **Revisión de estrategia comercial** (Remota, $3,200)
  - Plan de crecimiento 12-24 meses

## Datos incluidos

El seeder crea automáticamente:

✅ **7 usuarios** (3 clientes, 4 profesionales)  
✅ **4 perfiles profesionales** con biografías  
✅ **4 empresas/consultorios** con información de contacto  
✅ **13 servicios** (12 activos, 1 inactivo para testing)  
✅ **Disponibilidad semanal** realista para cada servicio  
✅ **Excepciones de disponibilidad** (días no disponibles, horarios alternativos)  
✅ **60+ reservas** distribuidas en todos los estados (pending, confirmed, paid, in_progress, completed, cancelled, no_show)  
✅ **5+ reviews** para bookings completados  
✅ **3+ respuestas profesionales** a reviews  
✅ **Ratings recalculados** automáticamente  

### Pagos y paquetes incluidos

El seeder tambien crea:

- **8 paquetes de sesiones** publicados por profesionales.
- **4 paquetes comprados** por clientes en estados `active`, `depleted` y `expired`.
- **Package sessions** en estados `reserved`, `consumed` y `released`.
- **Payment intents** en estados `succeeded`, `pending` y `failed`.
- **Payments succeeded** asociados solo a reservas individuales `paid`.
- Ningun payment para reservas cubiertas por `client_package_id`.

### Paquetes demo

Profesionales demo tienen paquetes como:

- `Pack 4 sesiones de terapia online`
- `Pack 8 sesiones de acompañamiento terapéutico`
- `Pack 4 sesiones de coaching ejecutivo`
- `Pack 3 consultas nutricionales`
- `Pack 5 mentorías de negocio`
- `Pack diagnóstico + estrategia` queda inactivo para validar listados publicos.

### Clientes con paquetes

- `cliente@proconnect.test` tiene un paquete activo con sesiones disponibles.
- `cliente2@proconnect.test` tiene un paquete activo con sesiones consumidas, reservadas y liberadas.
- `cliente3@proconnect.test` tiene un paquete `depleted` y otro `expired`.

### Pagos demo

El seeder crea:

- `payments` succeeded para reservas `paid` sin paquete.
- `payment_intents` pending para probar checkout pendiente.
- `payment_intents` failed para probar reintento de pago.
- `payment_intents` succeeded vinculados a los pagos demo.

## Video Sessions

El sistema soporta sesiones virtuales simuladas para reservas remotas o hibridas. La sala se crea automaticamente al confirmar o pagar una reserva compatible, y tambien puede asegurarse manualmente con un endpoint idempotente.

### Crear sala

```http
POST /api/v1/bookings/{booking}/video-session
```

### Unirse

```http
POST /api/v1/video-sessions/{videoSession}/join
```

El token de acceso simulado se genera solo al hacer join autenticado. No se envia por email.

### Mis sesiones

```http
GET /api/v1/video-sessions/my
```

### Sesiones del profesional

```http
GET /api/v1/professional/video-sessions
```

### Configuracion

```env
VIDEO_PROVIDER=simulator
VIDEO_JOIN_BEFORE_MINUTES=15
VIDEO_JOIN_AFTER_MINUTES=120
VIDEO_SIMULATOR_BASE_URL=http://localhost:4200/video
```

## Validar datos demo

Después de ejecutar `migrate:fresh --seed`:

### Login

```bash
# Como cliente
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "cliente@proconnect.test",
    "password": "password123"
  }'

# Como profesional
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "psicologa@proconnect.test",
    "password": "password123"
  }'
```

### Marketplace

```bash
# Ver servicios activos (público)
GET http://localhost:8000/api/v1/public/services

# Detalle de servicio
GET http://localhost:8000/api/v1/public/services/{service-id}
```

### Disponibilidad

```bash
# Ver slots disponibles
GET http://localhost:8000/api/v1/services/{service-id}/availability?date=2024-01-15
```

### Reviews

```bash
# Ver reviews de un servicio
GET http://localhost:8000/api/v1/services/{service-id}/reviews
```

## Protección de producción

Los seeders de demo **NO se ejecutarán en producción** por seguridad:

```php
// DatabaseSeeder.php
if (app()->environment('production')) {
    return;
}
```

Además, puedes desactivar completamente el seeding demo con:

```env
SEED_DEMO_DATA=false
```

---

# Base NoSQL complementaria para logs

ProConnect incorpora MongoDB como base documental complementaria para actividad,
auditoria y eventos operativos. PostgreSQL sigue siendo la fuente de verdad
transaccional para usuarios, perfiles, servicios, reservas, pagos, paquetes,
videollamadas y resenas. Redis continua dedicado a cache, colas, locks, rate
limiting y Horizon.

MongoDB almacena eventos de autenticacion y OAuth, reservas, pagos, paquetes,
servicios, disponibilidad, perfiles profesionales, videollamadas, resenas,
notificaciones, seguridad y errores internos. El `ActivityLogger` sanitiza
passwords, tokens, secretos y datos de pago. Si MongoDB no esta disponible, el
flujo principal continua y Laravel escribe un warning en su log.

## Actor global vs capacidad funcional en logs

Cada documento conserva dos dimensiones diferentes del actor:

- `actor_role`: rol global persistido en PostgreSQL (`client`, `professional` o
  `admin`).
- `acting_as`: modo funcional usado durante la accion (`guest`, `client`,
  `professional`, `admin` o `system`).

Por ejemplo, un usuario con `actor_role=professional` puede reservar o pagar un
servicio ajeno con `acting_as=client`. Al crear su propio servicio mantiene
`actor_role=professional` y usa `acting_as=professional`. Los procesos internos
usan `acting_as=system`, mientras que un login fallido usa `acting_as=guest`.

## Configuracion

```env
MONGODB_USERNAME=proconnect
MONGODB_PASSWORD=secret
MONGODB_DATABASE=proconnect_logs
MONGODB_PORT=27017
MONGODB_URI=mongodb://proconnect:secret@proconnect_mongodb:27017/proconnect_logs?authSource=admin
```

Levantar MongoDB y crear los indices:

```bash
docker compose up -d proconnect_mongodb
docker compose exec proconnect_laravel php artisan activity-logs:ensure-indexes
```

Consultar desde Mongo:

```bash
docker compose exec proconnect_mongodb mongosh \
  -u proconnect -p secret --authenticationDatabase admin
```

```javascript
use proconnect_logs
db.activity_logs.find().sort({ created_at: -1 }).limit(20).pretty()
```

Los usuarios con rol `admin` tambien pueden consultar y filtrar los logs:

```http
GET /api/v1/admin/activity-logs?event=booking.created&severity=info&limit=50
Authorization: Bearer {TOKEN_ADMIN}
```

Filtros disponibles: `event`, `actor_id`, `entity_type`, `entity_id`,
`acting_as`, `severity` y `limit` (maximo 200).

Ejemplos:

```http
GET /api/v1/admin/activity-logs?acting_as=client&limit=50
GET /api/v1/admin/activity-logs?actor_id={USER_UUID}&acting_as=professional
```

---

select
  pwe.id as webhook_event_id,
  pwe.provider_event_id,
  pwe.event_type,
  pwe.resource_type,
  pwe.resource_id,
  pwe.signature_valid,
  pwe.status as webhook_status,
  pwe.failure_reason as webhook_failure_reason,
  pwe.created_at as webhook_created_at,

  p.id as payment_id,
  p.status as payment_status,
  p.provider,
  p.provider_reference,
  p.provider_payment_id,
  p.raw_provider_status,
  p.amount,
  p.currency,
  p.paid_at,

  pi.id as payment_intent_id,
  pi.status as payment_intent_status,
  pi.provider_reference as intent_provider_reference,
  pi.checkout_url,

  b.id as booking_id,
  b.status as booking_status
from payment_webhook_events pwe
left join payments p
  on p.provider = pwe.provider
 and p.provider_payment_id = pwe.resource_id
left join payment_intents pi
  on pi.id = p.payment_intent_id
left join bookings b
  on b.id = coalesce(pi.booking_id, p.booking_id)
<!-- where pwe.provider = 'mercadopago'
  and pwe.resource_id = '163096411563' -->
order by pwe.created_at desc;
