# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

A Laravel package (`alitvinenko/laravel-pachka-logging`) that ships a Monolog handler sending log records to the Pachka messenger via an incoming webhook. It is a library — there is no application to run; everything is exercised through tests using `orchestra/testbench`.

## Commands

```bash
composer install                              # vendor/ is gitignored; install before anything else
vendor/bin/phpunit                            # full suite
vendor/bin/phpunit --filter test_handler_sends_message_to_webhook   # single test
vendor/bin/phpunit tests/Jobs/SendPachkaMessageTest.php             # single file
vendor/bin/pint                               # format (Laravel Pint)
vendor/bin/pint --test                        # check formatting, as CI does
vendor/bin/phpstan analyse                    # larastan level 8 over src/
```

CI (`.github/workflows/ci.yml`) runs pint `--test`, phpstan, and the test matrix across PHP 8.1–8.4 × Laravel 10–13. Laravel 10/11 rows install with `--no-security-blocking` because those are EOL. Releases are cut by pushing a `v*` tag.

## Architecture

The flow is: Laravel logging channel → `PachkaLogger` (factory) → `PachkaHandler` (Monolog handler) → Blade template → Guzzle POST, either inline or via a queued job.

- **`PachkaLogger`** — the `via` callable for a `driver => custom` logging channel. Reads settings with per-channel config taking precedence over `config/pachka-logger.php` (`$config['async'] ?? config('pachka-logger.async', ...)`), builds the handler, and attaches `IntrospectionProcessor` (skipping `Illuminate\`, `Monolog\`, `NunoMaduro\`, `Symfony\` frames so `$extra['file']` points at app code) plus `WebProcessor` (URL/method/IP).
- **`PachkaHandler`** — formats the record, splits it into 4096-char chunks, and sends each chunk. Formatting renders the configured Blade view with the record array merged with `appName`, `appEnv`, `formatted`, and a `context` where `Throwable`s are flattened to `class`/`message`/`file`/`trace` (first 5 non-vendor frames). Any rendering failure falls back to a hardcoded `sprintf` format — templates must never be able to break logging.
- **`SendPachkaMessage`** — the queued job used when `async` is on: 5 tries, `[10, 30]` backoff. Formatting happens synchronously in the request; only the HTTP call is deferred. A `429` response is not treated as an error: the job is released for the `Retry-After` delay (delay-seconds or HTTP-date, defaulting to 10s) and only `fail()`s once `attempts() >= $tries`. Every other Guzzle error is logged and swallowed — the job succeeds and the message is dropped.
- **`PachkaLoggerServiceProvider`** — auto-discovered; merges config, registers the `pachka-logging` view namespace, and exposes the `pachka-logger-config` / `pachka-logger-views` publish tags.

### Constraints to preserve

- **Only `429` is retried.**  The synchronous path (`PachkaHandler::sendMessage()`) has no retry at all — it must not block the request thread. Any retry logic belongs in the job.
- **Webhook failures must never throw.** Both `PachkaHandler::sendMessage()` and `SendPachkaMessage::handle()` catch `GuzzleException` and report to `Log::channel('single')` — logging a log failure through the Pachka channel would recurse.
- **`illuminate/queue` is only a `suggest`.** Async paths must stay optional; the handler constructor guards with `interface_exists(Dispatcher::class)` and throws a clear `RuntimeException` when async is requested without it.
- **Templates are part of the public API.** `standard.blade.php` and `minimal.blade.php` can be published into an app, and users write their own against the documented variables (`$appName`, `$appEnv`, `$level_name`, `$datetime`, `$message`, `$context`, `$extra`, `$formatted`). Renaming or repurposing one of those is a breaking change; update README.md alongside.

## Testing notes

Tests extend `Orchestra\Testbench\TestCase` and register the provider via `getPackageProviders()`. Guzzle is mocked through `MockHandler` + `Middleware::history` and injected with `PachkaHandler::setHttpClient()` (instance) or `SendPachkaMessage::setHttpClient()` (static — reset it to `null` in `tearDown`). Note that `PachkaHandler::write()` only dispatches a job when `httpClient === null`, so an injected client forces the synchronous path; assert async behaviour with `Bus::fake()` and no injected client.
