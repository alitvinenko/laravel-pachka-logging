<?php

declare(strict_types=1);

namespace Pachka\Logging\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class SendPachkaMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const TOO_MANY_REQUESTS = 429;

    /** Delay used when a 429 response carries no usable Retry-After header. */
    private const DEFAULT_RETRY_AFTER = 10;

    public int $tries = 5;

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
            $response = $e instanceof RequestException ? $e->getResponse() : null;

            if ($response !== null && $response->getStatusCode() === self::TOO_MANY_REQUESTS) {
                $this->handleRateLimit($response, $e);

                return;
            }

            Log::channel('single')->error('Pachka webhook request failed: '.$e->getMessage());
        }
    }

    /**
     * Re-queues the job after the delay Pachka asked for, or fails it once the attempts are used up.
     */
    private function handleRateLimit(ResponseInterface $response, GuzzleException $e): void
    {
        if ($this->attempts() >= $this->tries) {
            Log::channel('single')->error(sprintf(
                'Pachka webhook rate limited (429), giving up after %d attempts.',
                $this->attempts(),
            ));

            $this->fail($e);

            return;
        }

        $delay = $this->retryAfterSeconds($response);

        Log::channel('single')->warning(sprintf(
            'Pachka webhook rate limited (429), retrying in %ds (attempt %d/%d).',
            $delay,
            $this->attempts(),
            $this->tries,
        ));

        $this->release($delay);
    }

    /**
     * Reads Retry-After, which RFC 9110 allows as either delay-seconds or an HTTP-date.
     */
    private function retryAfterSeconds(ResponseInterface $response): int
    {
        $header = trim($response->getHeaderLine('Retry-After'));

        if ($header === '') {
            return self::DEFAULT_RETRY_AFTER;
        }

        if (ctype_digit($header)) {
            return max(1, (int) $header);
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return self::DEFAULT_RETRY_AFTER;
        }

        return max(1, $timestamp - time());
    }

    public static function setHttpClient(?Client $client): void
    {
        self::$testHttpClient = $client;
    }
}
