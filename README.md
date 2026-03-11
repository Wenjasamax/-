# Заявки в ремонтную службу

Небольшой веб-сервис для приема и обработки заявок с ролями `dispatcher` и `master`.

## Что реализовано

- Создание заявки (`new`) с обязательными полями: `clientName`, `phone`, `address`, `problemText`.
- Простая авторизация через выбор пользователя.
- Панель диспетчера:
  - список заявок,
  - фильтр по статусу,
  - назначение мастера (`new -> assigned`),
  - отмена заявки (`new/assigned -> canceled`).
- Панель мастера:
  - список заявок текущего мастера,
  - "Взять в работу" (`assigned -> in_progress`),
  - "Завершить" (`in_progress -> done`).
- Защита от гонки для действия "Взять в работу":
  - атомарный `UPDATE ... WHERE status='assigned'`,
  - при втором параллельном запросе возвращается `409 Conflict` на API-эндпоинте.
- Audit log (`request_events`) как дополнительный плюс.
- 2 автотеста (`phpunit`).

## Запуск через Docker Compose (предпочтительный)

```bash
docker compose up --build
```

Приложение будет доступно по адресу: `http://localhost:8000`.

При старте контейнера `app` автоматически выполняются:
- миграции (`scripts/migrate.php`),
- сиды (`scripts/seed.php`).

## Тестовые пользователи

- `Ирина Диспетчер` (`dispatcher`)
- `Павел Мастер` (`master`)
- `Олег Мастер` (`master`)

Выбор пользователя выполняется на странице входа `/login`.

## Проверка гонки (обязательный пункт)

### Вариант 1: два терминала с curl

1. Войти мастером (получить cookie):
```bash
curl -c cookie.txt -b cookie.txt -X POST http://localhost:8000/login -d "user_id=2"
```
2. Запустить одновременно в двух терминалах:
```bash
curl -i -c cookie.txt -b cookie.txt -X POST http://localhost:8000/api/master/take -d "request_id=2"
```

Ожидаемое поведение:
- один запрос: `HTTP/1.1 200 OK`,
- второй запрос: `HTTP/1.1 409 Conflict`.

### Вариант 2: скрипт

```bash
sh repair-service/scripts/race_test.sh http://localhost:8000 2 2
```

## Автотесты

Запуск тестов в контейнере:
```bash
docker compose run --rm app sh -lc "composer install && composer test"
```

## Локальный запуск без Docker (вариант B)

Требования: PHP 8.2+, MySQL 8+, Composer.

1. Установить зависимости:
```bash
cd repair-service
composer install
```
2. Настроить переменные окружения (`DB_*`) из `.env.example`.
3. Выполнить миграции и сиды:
```bash
php scripts/migrate.php
php scripts/seed.php
```
4. Запустить встроенный сервер PHP:
```bash
php -S localhost:8000 -t public
```

