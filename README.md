# Laravel Pachka Logging

Laravel package for sending logs to [Pachka](https://pachca.com) messenger via incoming webhook.

## Requirements

- PHP 8.1+
- Laravel 10, 11 or 12
- Monolog 3.x

## Installation

```bash
composer require core-services/laravel-pachka-logging
```

## Configuration

### 1. Set up Pachka webhook

1. Open Pachka and go to the channel where you want to receive error notifications
2. Channel settings → Integrations → Add integration → Incoming webhook
3. Copy the webhook URL

### 2. Environment variables

```env
PACHKA_LOGGER_WEBHOOK_URL=https://api.pachca.com/webhooks/incoming/YOUR_WEBHOOK_ID
```

Optional:
```env
PACHKA_LOGGER_TEMPLATE=pachka-logging::standard
PACHKA_LOGGER_TIMEOUT=10
```

### 3. Add logging channel

In `config/logging.php`:

```php
'pachka' => [
    'driver' => 'custom',
    'via' => Pachka\Logging\PachkaLogger::class,
    'level' => 'error',
],
```

### 4. Enable the channel

As the default channel:

```env
LOG_CHANNEL=pachka
```

Or add to a stack:

```env
LOG_STACK=daily,pachka
```

## Usage

Use standard Laravel logging — messages are sent to Pachka based on the configured level:

```php
Log::error('Payment processing failed', ['order_id' => 123]);
Log::critical('Database connection lost');
```

Unhandled exceptions are captured automatically via Laravel's exception handler.

## Message templates

Two built-in templates are available:

- `pachka-logging::standard` (default) — app name, environment, timestamp, call location, message and context as pretty-printed JSON
- `pachka-logging::minimal` — app name, level and message only

### Custom templates

Publish and edit the views, or create your own Blade template:

```bash
php artisan vendor:publish --tag=pachka-logger-views
```

```env
PACHKA_LOGGER_TEMPLATE=your-custom-view-name
```

Available template variables:
- `$appName` — application name
- `$appEnv` — environment (production, staging, etc.)
- `$level_name` — log level (ERROR, WARNING, etc.)
- `$datetime` — Carbon instance with date/time
- `$message` — original log message
- `$context` — context array (Throwable instances are serialized to arrays with class, message, file and trace)
- `$extra` — extra data (URL, HTTP method, IP from WebProcessor; file, line, class, function from IntrospectionProcessor)
- `$formatted` — full formatted string with message and context

### Publishing config

```bash
php artisan vendor:publish --tag=pachka-logger-config
```

## License

MIT
