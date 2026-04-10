<?php

declare(strict_types=1);

namespace Pachka\Logging;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class PachkaHandler extends AbstractProcessingHandler
{
    private const MAX_MESSAGE_LENGTH = 4096;

    private string $webhookUrl;

    private string $appName;

    private string $appEnv;

    private ?Client $httpClient = null;

    public function __construct(
        string $webhookUrl,
        string $appName,
        string $appEnv,
        Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        $this->webhookUrl = $webhookUrl;
        $this->appName = $appName;
        $this->appEnv = $appEnv;
    }

    public function setHttpClient(Client $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    protected function write(LogRecord $record): void
    {
        $text = $this->formatText($record);
        $chunks = str_split($text, self::MAX_MESSAGE_LENGTH);

        foreach ($chunks as $chunk) {
            $this->sendMessage($chunk);
        }
    }

    private function formatText(LogRecord $record): string
    {
        try {
            $template = config('pachka-logger.template', 'pachka-logging::standard');

            if ($template && View::exists($template)) {
                return View::make($template, array_merge($record->toArray(), [
                    'appName' => $this->appName,
                    'appEnv' => $this->appEnv,
                    'formatted' => $record->formatted ?? $record->message,
                    'context' => $this->serializeContext($record->context),
                ]))->render();
            }
        } catch (\Throwable) {
            // Fallback to plain format if Blade rendering fails
        }

        return sprintf(
            "**%s** (%s)\nEnv: %s\n[%s] %s.%s %s",
            $this->appName,
            $record->level->name,
            $this->appEnv,
            $record->datetime->format('Y-m-d H:i:s'),
            $this->appEnv,
            $record->level->name,
            $record->formatted ?? $record->message,
        );
    }

    private function sendMessage(string $text): void
    {
        $client = $this->httpClient ?? new Client([
            'timeout' => config('pachka-logger.timeout', 10),
        ]);

        try {
            $client->post($this->webhookUrl, [
                'json' => [
                    'message' => $text,
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::channel('single')->error('Pachka webhook request failed: '.$e->getMessage());
        }
    }

    /**
     * Converts Throwable instances in context to serializable arrays with trace.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function serializeContext(array $context): array
    {
        return array_map(function (mixed $value): mixed {
            if (! $value instanceof \Throwable) {
                return $value;
            }

            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'file' => $value->getFile().':'.$value->getLine(),
                'trace' => array_map(
                    fn(array $frame): string => sprintf(
                        '%s%s%s() %s:%s',
                        $frame['class'] ?? '',
                        $frame['type'] ?? '',
                        $frame['function'] ?? '',
                        $frame['file'] ?? '[internal]',
                        $frame['line'] ?? '',
                    ),
                    array_slice($value->getTrace(), 0, 10),
                ),
            ];
        }, $context);
    }

    protected function getDefaultFormatter(): LineFormatter
    {
        return new LineFormatter("%message% %context% %extra%\n", null, true, true);
    }
}
