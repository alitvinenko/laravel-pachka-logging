<?php

declare(strict_types=1);

namespace Pachka\Logging\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPachkaMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    private static ?Client $testHttpClient = null;

    public function __construct(
        public readonly string $webhookUrl,
        public readonly string $text,
        public readonly int $timeout = 10,
    ) {}

    public function handle(): void
    {
        $client = self::$testHttpClient ?? new Client([
            'timeout' => $this->timeout,
        ]);

        try {
            $client->post($this->webhookUrl, [
                'json' => [
                    'message' => $this->text,
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::channel('single')->error('Pachka webhook request failed: '.$e->getMessage());
        }
    }

    public static function setHttpClient(?Client $client): void
    {
        self::$testHttpClient = $client;
    }
}
