# Laravel Pachka Logging

Laravel-пакет для отправки логов в мессенджер [Пачка](https://pachca.com) через входящий вебхук.

## Требования

- PHP 8.1+
- Laravel 10, 11 или 12
- Monolog 3.x

## Установка

### Подключение из GitLab

Добавьте репозиторий в `composer.json` вашего проекта:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitlab.affilyx.com/core-services/laravel-pachka-logging.git"
        }
    ]
}
```

Настройте аутентификацию. Создайте или отредактируйте `auth.json` в корне проекта:

```json
{
    "http-basic": {
        "gitlab.affilyx.com": {
            "username": "___token___",
            "password": "<ваш-deploy-token>"
        }
    }
}
```

Или глобально:

```bash
composer config --global --auth http-basic.gitlab.affilyx.com ___token___ <ваш-deploy-token>
```

Установите пакет:

```bash
composer require core-services/laravel-pachka-logging
```

### Публикация конфига (опционально)

```bash
php artisan vendor:publish --tag=pachka-logger-config
```

### Публикация шаблонов (опционально)

```bash
php artisan vendor:publish --tag=pachka-logger-views
```

## Настройка

### 1. Переменные окружения

```env
PACHKA_LOGGER_WEBHOOK_URL=https://api.pachca.com/webhooks/incoming/YOUR_WEBHOOK_ID
```

Опционально:
```env
PACHKA_LOGGER_TEMPLATE=pachka-logging::standard
PACHKA_LOGGER_TIMEOUT=10
```

### 2. Добавить канал логирования

В `config/logging.php` добавьте канал `pachka`:

```php
'pachka' => [
    'driver' => 'custom',
    'via' => Pachka\Logging\PachkaLogger::class,
    'level' => 'error',
],
```

### 3. Включить канал

Установить как основной канал:

```env
LOG_CHANNEL=pachka
```

Или добавить в стек:

```env
LOG_STACK=single,pachka
```

## Шаблоны сообщений

Доступны два встроенных шаблона:

- `pachka-logging::standard` (по умолчанию) — название приложения, окружение, timestamp и полный текст ошибки в блоке кода
- `pachka-logging::minimal` — только название приложения и сообщение

### Кастомные шаблоны

Опубликуйте шаблоны и отредактируйте, или создайте свой Blade-шаблон и укажите его:

```env
PACHKA_LOGGER_TEMPLATE=your-custom-view-name
```

Доступные переменные в шаблонах:
- `$appName` — название приложения
- `$appEnv` — окружение (production, staging и т.д.)
- `$level_name` — уровень лога (ERROR, WARNING и т.д.)
- `$datetime` — объект Carbon с датой/временем
- `$formatted` — отформатированное сообщение с контекстом
- `$message` — исходное сообщение
- `$context` — массив контекста
- `$extra` — массив дополнительных данных

## Настройка вебхука в Пачке

1. Откройте Пачку и перейдите в канал/чат, куда хотите получать уведомления об ошибках
2. Настройки канала -> Интеграции -> Добавить интеграцию -> Входящий вебхук
3. Скопируйте URL вебхука
4. Укажите его в `.env` как `PACHKA_LOGGER_WEBHOOK_URL`

## Использование

После настройки просто используйте стандартное логирование Laravel:

```php
// Сообщения будут отправляться в Пачку в зависимости от настроенного уровня
Log::error('Payment processing failed', ['order_id' => 123]);
Log::critical('Database connection lost');
```

## Лицензия

MIT
