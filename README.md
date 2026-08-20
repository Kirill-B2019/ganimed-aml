# AML Screening

Данные рисков — GoPlus Security. Балансы и поступления по Tron — [TronGrid](https://www.trongrid.io/).

## Стек

- PHP 8.2+, Laravel 12, Blade, MySQL
- Vite 6, Axios, Tailwind, Alpine
- Queue: `database`
- PHPUnit 11, Pint, Collision, Sail

## Локальный запуск (XAMPP)

1. Создайте БД `aml_gnd` в MySQL.
2. Скопируйте `.env.example` → `.env`, заполните `GOPLUS_APP_KEY` / `GOPLUS_APP_SECRET`, `php artisan key:generate`.
3. `composer install` (или `php path\to\composer.phar install`)
4. `npm install && npm run build`
5. `php artisan migrate --seed`
6. Порт **8000 на этой машине занят GAMIMED Hub**. AML поднимайте отдельно:
   - `php artisan serve --host=127.0.0.1 --port=8088`
   - `php artisan queue:work database`
7. Откройте **http://127.0.0.1:8088/login** (не `localhost:8000` и не `http://localhost` без порта).
8. Вход: `ADMIN_EMAIL` / `ADMIN_PASSWORD` из `.env` (по умолчанию `admin@localhost` / `ChangeMe123!`). В шапке страницы должно быть «AML-скрининг», не «GAMIMED Hub».

Публичной регистрации нет. Новый пользователь: страница «Пользователи» (админ) или `php artisan user:create "Name" email@host password --admin`.

## Sail

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev
```

Mailpit UI: http://localhost:8025. На Windows порт MySQL 3306 может быть занят XAMPP — смените `FORWARD_DB_PORT`.

## API

Токен выпускается в кабинете (API tokens), затем:

```http
Authorization: Bearer {token}
POST /api/v1/checks/address
{ "address": "0x...", "chain_id": "1" }
```

Остальные маршруты: `/token`, `/phishing`, `/dapp`, `/scan`, `GET /api/v1/checks/{id}`, `GET /api/v1/checks/{id}/pdf`.

Глубокий scan ставится в очередь (`202`) и доходит до `completed` воркером.

## Тесты и стиль

```bash
php artisan test
vendor/bin/pint
```

## Прод (VDS + nginx + Cloudflare)

См. `deploy/nginx.conf` и `deploy/aml-queue.service`.

- Cloudflare: proxy, SSL Full (strict), HTTPS redirect
- `QUEUE_CONNECTION=database`
- systemd: `deploy/aml-queue.service` + `deploy/aml-scheduler.timer`
- Не коммитить `.env`
