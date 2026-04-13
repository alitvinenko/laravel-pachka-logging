<?php

declare(strict_types=1);

namespace Pachka\Logging\Tests\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use Pachka\Logging\Jobs\SendPachkaMessage;
use Pachka\Logging\PachkaLoggerServiceProvider;

class SendPachkaMessageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PachkaLoggerServiceProvider::class];
    }

    protected function tearDown(): void
    {
        SendPachkaMessage::setHttpClient(null);
        parent::tearDown();
    }

    public function test_job_sends_http_post(): void
    {
        // Arrange
        $history = [];
        $mock = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack]);

        SendPachkaMessage::setHttpClient($httpClient);

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
            timeout: 5,
        );

        // Act
        $job->handle();

        // Assert
        $this->assertCount(1, $history);

        $request = $history[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('https://api.pachca.com/webhooks/incoming/test', (string) $request->getUri());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals(['message' => 'Test message'], $body);
    }

    public function test_job_handles_http_failure_gracefully(): void
    {
        // Arrange
        $mock = new MockHandler([new Response(500, [], 'Internal Server Error')]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        SendPachkaMessage::setHttpClient($httpClient);

        $job = new SendPachkaMessage(
            webhookUrl: 'https://api.pachca.com/webhooks/incoming/test',
            text: 'Test message',
        );

        // Act & Assert — should not throw
        $job->handle();
        $this->assertTrue(true);
    }
}
