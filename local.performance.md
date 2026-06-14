# ProConnect - Local WSL Deploy

Guía mínima para levantar el backend local usando Docker desde WSL.

## 1. Entrar al proyecto

```bash
cd ~/ProConnect
```

> Importante: ejecutar desde filesystem Linux de WSL, no desde `/mnt/c/...`.

## 2. Levantar contenedores

```bash
docker compose up -d --build
```

## 3. Limpiar y cachear Laravel

```bash
docker compose exec proconnect_laravel php artisan optimize:clear
docker compose exec proconnect_laravel php artisan config:cache
docker compose exec proconnect_laravel php artisan route:cache
docker compose exec proconnect_laravel php artisan view:cache
```

## 4. Migrar y seedear base de datos

```bash
docker compose exec proconnect_laravel php artisan migrate:fresh --seed
```

## 5. Verificar estado

```bash
docker compose ps
```

## 6. Probar API

```bash
curl http://localhost/api/v1/public/services
```

## Comandos útiles

Ver logs de Laravel:

```bash
docker compose logs -f proconnect_laravel
```

Ver logs de Horizon:

```bash
docker compose logs -f proconnect_horizon
```

Bajar contenedores:

```bash
docker compose down
```
