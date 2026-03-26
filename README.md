# Laravel Pachka Logging

Send Laravel log messages to [Pachka](https://pachca.com) messenger via incoming webhook.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Monolog 3.x

## Installation

### From GitLab (private registry)

Add the repository to your project's `composer.json`:

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

Then configure authentication. Create or edit `auth.json` in your project root:

```json
{
    "http-basic": {
        "gitlab.affilyx.com": {
            "username": "___token___",
            "password": "<your-gitlab-deploy-token>"
        }
    }
}
```

Or set it globally:

```bash
composer config --global --auth http-basic.gitlab.affilyx.com ___token___ <your-deploy-token>
```

Install the package:

```bash
composer require core-services/laravel-pachka-logging
```

### Publish config (optional)

```bash
php artisan vendor:publish --tag=pachka-logger-config
```

### Publish views (optional)

```bash
php artisan vendor:publish --tag=pachka-logger-views
```

## Configuration

### 1. Set environment variables

```env
PACHKA_LOGGER_WEBHOOK_URL=https://api.pachca.com/webhooks/incoming/YOUR_WEBHOOK_ID
```

Optional:
```env
PACHKA_LOGGER_TEMPLATE=pachka-logging::standard
PACHKA_LOGGER_TIMEOUT=10
```

### 2. Add the logging channel

In `config/logging.php`, add the `pachka` channel:

```php
'pachka' => [
    'driver' => 'custom',
    'via' => Pachka\Logging\PachkaLogger::class,
    'level' => 'error',
],
```

### 3. Enable the channel

Either set it as the default channel:

```env
LOG_CHANNEL=pachka
```

Or add it to your stack:

```env
LOG_STACK=single,pachka
```

## Message templates

Two built-in templates are available:

- `pachka-logging::standard` (default) — includes app name, environment, timestamp, and full log message in a code block
- `pachka-logging::minimal` — app name and message only

### Custom templates

Publish the views and edit them, or create your own Blade template and reference it:

```env
PACHKA_LOGGER_TEMPLATE=your-custom-view-name
```

Available variables in templates:
- `$appName` — application name
- `$appEnv` — application environment
- `$level_name` — log level (ERROR, WARNING, etc.)
- `$datetime` — Carbon datetime instance
- `$formatted` — formatted log message with context
- `$message` — raw log message
- `$context` — log context array
- `$extra` — extra data array

## Setting up Pachka webhook

1. Open Pachka and go to the channel/chat where you want to receive error notifications
2. Go to channel settings → Integrations → Add integration → Incoming webhook
3. Copy the webhook URL
4. Set it as `PACHKA_LOGGER_WEBHOOK_URL` in your `.env`

## Usage

Once configured, just use Laravel's standard logging:

```php
// These will be sent to Pachka based on your configured level
Log::error('Payment processing failed', ['order_id' => 123]);
Log::critical('Database connection lost');
```

## License

MIT
